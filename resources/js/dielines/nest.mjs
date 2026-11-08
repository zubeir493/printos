import fs from 'node:fs';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import ClipperLib from 'clipper-lib';
import { area, extractOutline, integerPath, intersectionArea, normalPath, offset } from './outline.mjs';

// SVGnest MIT sources, revision 1248dc21efd3f90d1aa52ba5785e27e5217ed2c9.
// https://github.com/Jack000/SVGnest — license preserved beside the sources.
const sandbox = { ClipperLib, console: { log() {} } };
sandbox.self = sandbox;
vm.createContext(sandbox);
for (const file of ['geometryutil.js', 'placementworker.js']) {
    vm.runInContext(fs.readFileSync(new URL(`./vendor/svgnest/${file}`, import.meta.url), 'utf8'), sandbox);
}
const { GeometryUtil, PlacementWorker, rotatePolygon } = sandbox;
const SCALE = 10000;
const bounds = polygon => GeometryUtil.getPolygonBounds(polygon);
const shifted = (polygon, placement) => rotatePolygon(polygon, placement.rotation).map(p => ({ x: p.x + placement.x, y: p.y + placement.y }));

export function validatePlacements(outline, placements, sheet, margin, gap) {
    const reserved = offset(outline, gap / 2);
    const polygons = [];
    for (const p of placements) {
        if (![p.x, p.y, p.rotation].every(Number.isFinite) || ![0, 90, 180, 270].includes(p.rotation)) throw new Error('Invalid placement.');
        const raw = shifted(outline, p);
        if (raw.some(q => q.x < margin - 0.01 || q.y < margin - 0.01 || q.x > sheet.width - margin + 0.01 || q.y > sheet.height - margin + 0.01)) throw new Error('A dieline crosses the sheet margin.');
        const polygon = shifted(reserved, p);
        for (const other of polygons) {
            const a = bounds(polygon), b = bounds(other);
            if (a.x + a.width <= b.x || b.x + b.width <= a.x || a.y + a.height <= b.y || b.y + b.height <= a.y) continue;
            if (intersectionArea(polygon, other) > 0.00001) throw new Error('Dielines overlap or do not meet the required spacing.');
        }
        polygons.push(polygon);
    }
}

/** Slide actual outlines into contact, including layouts initially arranged by bounds. */
export function compactPlacements(outline, placements, sheet, margin = 0, budgetMs = 5000) {
    const result = placements.map(p => ({ ...p }));
    const polygons = result.map(p => shifted(outline, p));
    const boxes = polygons.map(bounds);
    const deadline = Date.now() + budgetMs;
    const order = result.map((_, i) => i).sort((a, b) => boxes[a].y - boxes[b].y || boxes[a].x - boxes[b].x);
    for (let pass = 0; pass < 2 && Date.now() < deadline; pass++) for (const i of order) for (const axis of ['x', 'y']) {
        if (Date.now() >= deadline) break;
        const maximum = boxes[i][axis] - margin;
        if (maximum <= 0.00001) continue;
        const current = polygons[i];
        function valid(distance) {
            const box = { ...boxes[i], [axis]: boxes[i][axis] - distance };
            let moved;
            for (let j = 0; j < polygons.length; j++) {
                if (i === j) continue;
                const other = boxes[j];
                if (box.x + box.width <= other.x || other.x + other.width <= box.x || box.y + box.height <= other.y || other.y + other.height <= box.y) continue;
                moved ??= current.map(p => ({ ...p, [axis]: p[axis] - distance }));
                if (intersectionArea(moved, polygons[j]) > 0.00001) return false;
            }
            return true;
        }
        let low = 0, high = maximum;
        if (valid(high)) low = high;
        else for (let step = 0; step < 30 && high - low > 0.000001; step++) {
            const middle = (low + high) / 2;
            if (valid(middle)) low = middle; else high = middle;
        }
        result[i][axis] -= low;
        polygons[i] = current.map(p => ({ ...p, [axis]: p[axis] - low }));
        boxes[i] = bounds(polygons[i]);
    }
    validatePlacements(outline, result, sheet, margin, 0);
    return result;
}

export function nest(request) {
    const { geometry, sheet } = request;
    const margin = request.margin ?? 0, gap = request.gap ?? 0;
    const started = Date.now(), searchMs = Math.min(25000, Math.max(100, request.search_ms ?? 5000));
    const deadline = started + searchMs, placementDeadline = started + searchMs * 0.4;
    const refillDeadline = started + searchMs * 0.65, beamDeadline = started + searchMs * 0.9;
    const outline = extractOutline(geometry);
    // A simplified search envelope keeps high-resolution curves tractable.
    // Zero-gap results are then compacted against the untouched outline, so
    // this approximation never imposes clearance on the exported layout.
    const expanded = offset(outline, gap / 2 + 0.15);
    const polygon = normalPath(ClipperLib.Clipper.CleanPolygon(integerPath(expanded), 0.1 * SCALE));
    if (GeometryUtil.polygonArea(polygon) > 0) polygon.reverse();
    const origin = Math.max(0, margin - gap / 2 - 0.15);
    const width = sheet.width - 2 * origin, height = sheet.height - 2 * origin;
    const bin = [{ x: 0, y: 0 }, { x: width, y: 0 }, { x: width, y: height }, { x: 0, y: height }];
    if (GeometryUtil.polygonArea(bin) > 0) bin.reverse();
    const rotations = [0, 90, 180, 270];
    const maximum = Math.min(request.max_copies ?? 500, Math.floor(width * height / area(outline)));
    const cache = {};
    function nfp(key) {
        if (cache[key]) return cache[key];
        const k = JSON.parse(key), b = rotatePolygon(polygon, k.Brotation);
        let result;
        if (k.inside) result = GeometryUtil.noFitPolygonRectangle(bin, b) ?? [];
        else {
            const a = integerPath(rotatePolygon(polygon, k.Arotation));
            const negative = integerPath(b).map(p => ({ X: -p.X, Y: -p.Y }));
            const solutions = ClipperLib.Clipper.MinkowskiSum(a, negative, true);
            solutions.sort((a, b) => Math.abs(ClipperLib.Clipper.Area(b)) - Math.abs(ClipperLib.Clipper.Area(a)));
            result = solutions.length ? [normalPath(solutions[0]).map(p => ({ x: p.x + b[0].x, y: p.y + b[0].y }))] : [];
        }
        for (const p of result) if (GeometryUtil.polygonArea(p) > 0) p.reverse();
        return cache[key] = result;
    }
    let best = [], bestFootprint = Infinity;
    const seeds = [];
    // A fast starting layout, followed by alternating and mixed orientations.
    for (const rotation of rotations) {
        const box = bounds(rotatePolygon(outline, rotation)), placements = [];
        const columns = Math.floor((sheet.width - 2 * margin + gap) / (box.width + gap));
        const rows = Math.floor((sheet.height - 2 * margin + gap) / (box.height + gap));
        for (let row = 0; row < rows; row++) for (let column = 0; column < columns && placements.length < maximum; column++) {
            placements.push({ x: margin - box.x + column * (box.width + gap), y: margin - box.y + row * (box.height + gap), rotation });
        }
        if (placements.length > best.length) best = placements;
        if (placements.length) seeds.push(placements);
    }
    for (let attempt = 0; attempt < 40 && Date.now() < placementDeadline; attempt++) {
        const sequence = attempt < 4 ? [rotations[attempt]] : attempt < 8 ? [rotations[attempt % 4], rotations[(attempt + 2) % 4]] : rotations.map((_, i) => rotations[(i + attempt) % 4]);
        const paths = Array.from({ length: maximum }, (_, i) => Object.assign(polygon.slice(), { id: 0, source: 0, rotation: sequence[(i + Math.floor(i / (attempt % 7 + 1))) % sequence.length] }));
        const worker = new PlacementWorker(bin, paths, [], [], { clipperScale: SCALE, singleBin: true, deadline: placementDeadline }, new Proxy(cache, { get: (_, key) => nfp(key) }));
        sandbox.global = { env: { self: worker } };
        const result = worker.placePaths(paths);
        const placements = (result?.placements[0] ?? []).map(p => ({ x: p.x + origin, y: p.y + origin, rotation: p.rotation }));
        const footprint = placements.length ? bounds(placements.flatMap(p => shifted(outline, p))).width * bounds(placements.flatMap(p => shifted(outline, p))).height : Infinity;
        if (placements.length > best.length || (placements.length === best.length && footprint < bestFootprint)) {
            try { validatePlacements(outline, placements, sheet, margin, gap); }
            catch { continue; }
            best = placements; bestFootprint = footprint;
            if (seeds.length < 16) seeds.push(placements);
        }
        if (best.length === maximum) break;
    }
    if (!best.length) throw new Error('This dieline does not fit on the selected paper at 100% scale. Choose a larger paper size.');
    if (gap === 0) {
        const rotated = new Map(rotations.map(rotation => [rotation, rotatePolygon(outline, rotation)]));
        function refill(seed, until, branches = false) {
            let layout = branches ? seed : compactPlacements(outline, seed, sheet, margin, Math.max(10, until - Date.now()));
            while (layout.length < maximum && Date.now() < until) {
                const occupied = layout.map(p => shifted(outline, p));
                const boxes = occupied.map(bounds);
                let selected = null, selectedScore = Infinity;
                const choices = [];
                for (const rotation of rotations) {
                    if (Date.now() >= until) break;
                    const shape = rotated.get(rotation), shapeBox = bounds(shape);
                    const inside = nfp(JSON.stringify({ A: -1, B: 0, inside: true, Arotation: 0, Brotation: rotation }));
                    if (!inside.length) continue;
                    const obstacleUnion = new ClipperLib.Clipper(), obstacles = [];
                    for (const p of layout) {
                        const forbidden = nfp(JSON.stringify({ A: 0, B: 0, inside: false, Arotation: p.rotation, Brotation: rotation }));
                        obstacleUnion.AddPaths(forbidden.map(path => integerPath(path.map(q => ({ x: q.x + p.x - origin, y: q.y + p.y - origin })))), ClipperLib.PolyType.ptSubject, true);
                    }
                    obstacleUnion.Execute(ClipperLib.ClipType.ctUnion, obstacles, ClipperLib.PolyFillType.pftNonZero, ClipperLib.PolyFillType.pftNonZero);
                    const difference = new ClipperLib.Clipper(), available = [];
                    difference.AddPaths(inside.map(integerPath), ClipperLib.PolyType.ptSubject, true);
                    difference.AddPaths(obstacles, ClipperLib.PolyType.ptClip, true);
                    difference.Execute(ClipperLib.ClipType.ctDifference, available, ClipperLib.PolyFillType.pftNonZero, ClipperLib.PolyFillType.pftNonZero);
                    const anchor = rotatePolygon(polygon, rotation)[0];
                    const candidates = available.flatMap(normalPath).map(p => ({ x: p.x - anchor.x + origin, y: p.y - anchor.y + origin, rotation }));
                    for (const candidate of candidates) {
                        const box = { x: shapeBox.x + candidate.x, y: shapeBox.y + candidate.y, width: shapeBox.width, height: shapeBox.height };
                        if (box.x < margin - 0.001 || box.y < margin - 0.001 || box.x + box.width > sheet.width - margin + 0.001 || box.y + box.height > sheet.height - margin + 0.001) continue;
                        let moved, collision = false;
                        for (let i = 0; i < occupied.length; i++) {
                            const other = boxes[i];
                            if (box.x + box.width <= other.x || other.x + other.width <= box.x || box.y + box.height <= other.y || other.y + other.height <= box.y) continue;
                            moved ??= shape.map(p => ({ x: p.x + candidate.x, y: p.y + candidate.y }));
                            if (intersectionArea(moved, occupied[i]) > 0.00001) { collision = true; break; }
                        }
                        if (collision) continue;
                        const right = Math.max(box.x + box.width, ...boxes.map(b => b.x + b.width));
                        const bottom = Math.max(box.y + box.height, ...boxes.map(b => b.y + b.height));
                        const score = right * bottom + candidate.y * 0.001 + candidate.x * 0.0001;
                        choices.push({ candidate, score });
                        if (score < selectedScore) { selected = candidate; selectedScore = score; }
                    }
                }
                if (branches) {
                    choices.sort((a, b) => a.score - b.score);
                    return rotations.flatMap(rotation => choices.filter(p => p.candidate.rotation === rotation).slice(0, 5).map(p => p.candidate));
                }
                if (!selected) break;
                layout.push(selected);
                layout = compactPlacements(outline, layout, sheet, margin, Math.max(10, until - Date.now()));
            }
            return branches ? [] : layout;
        }
        const candidates = [best, ...seeds];
        best = compactPlacements(outline, best, sheet, margin, 200);
        for (let i = 0; i < candidates.length && Date.now() < refillDeadline; i++) {
            const until = Math.min(refillDeadline, Date.now() + Math.max(100, (refillDeadline - Date.now()) / (candidates.length - i)));
            const layout = refill(candidates[i], until);
            if (layout.length > best.length) best = layout;
            if (best.length === maximum) break;
        }
        // Retain alternative partial arrangements: the tightest four-copy
        // layout can block a fifth copy that fits in a different arrangement.
        let beam = [[]];
        const visited = new Set();
        while (beam.length && best.length < maximum && Date.now() < beamDeadline) {
            const next = [];
            for (const seed of beam) {
                if (Date.now() >= beamDeadline) break;
                for (const candidate of refill(seed, beamDeadline, true)) {
                    const layout = [...seed, candidate];
                    const key = layout.map(p => `${p.rotation}:${p.x.toFixed(3)}:${p.y.toFixed(3)}`).sort().join('|');
                    if (visited.has(key)) continue;
                    visited.add(key);
                    const box = bounds(layout.flatMap(p => shifted(outline, p)));
                    next.push({ layout, score: box.width * box.height, group: `${layout.filter(p => p.rotation % 180 === 0).length}:${Math.round(box.width / box.height * 4)}` });
                    if (layout.length > best.length) best = layout;
                }
            }
            next.sort((a, b) => a.score - b.score);
            const groups = new Map();
            beam = [];
            for (const state of next) {
                const count = groups.get(state.group) ?? 0;
                if (count >= 4) continue;
                groups.set(state.group, count + 1);
                beam.push(state.layout);
                if (beam.length >= 24) break;
            }
        }
        best = refill(best, deadline);
    }
    validatePlacements(outline, best, sheet, margin, gap);
    return { placements: best, copies: best.length, utilisation: Math.round(best.length * area(outline) / (sheet.width * sheet.height) * 10000) / 100, elapsed_ms: Date.now() - started, outline };
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    try { process.stdout.write(JSON.stringify(nest(JSON.parse(fs.readFileSync(0, 'utf8'))))); }
    catch (error) { process.stderr.write(error.message); process.exitCode = 1; }
}
