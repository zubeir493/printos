import ClipperLib from 'clipper-lib';
import { integerPath, normalPath, intersectionArea, offset } from './outline.mjs';

const tolerance = 0.0002;
const rotate = (point, angle) => {
    const cos = Math.round(Math.cos(angle * Math.PI / 180)), sin = Math.round(Math.sin(angle * Math.PI / 180));
    return { x: point.x * cos - point.y * sin, y: point.x * sin + point.y * cos };
};
const bounds = points => {
    const xs = points.map(p => p.x), ys = points.map(p => p.y);
    return { left: Math.min(...xs), right: Math.max(...xs), top: Math.min(...ys), bottom: Math.max(...ys) };
};
const separate = (a, b, distance = 0) => a.right + distance <= b.left || b.right + distance <= a.left || a.bottom + distance <= b.top || b.bottom + distance <= a.top;

/** Expand in sheet coordinates, so a rotated copy never swaps the user's X/Y spacing. */
function directionalEnvelope(points, horizontal, vertical) {
    if (!horizontal && !vertical) return points;
    const x = horizontal / 2, y = vertical / 2;
    const rectangle = integerPath([{ x: -x, y: -y }, { x, y: -y }, { x, y }, { x: -x, y }]);
    const original = integerPath(points);
    if (!ClipperLib.Clipper.Orientation(original)) original.reverse();
    const strokes = ClipperLib.Clipper.MinkowskiSum(rectangle, original, true);
    const clipper = new ClipperLib.Clipper(), paths = [];
    clipper.AddPaths([...strokes, original], ClipperLib.PolyType.ptSubject, true);
    clipper.Execute(ClipperLib.ClipType.ctUnion, paths, ClipperLib.PolyFillType.pftNonZero, ClipperLib.PolyFillType.pftNonZero);
    paths.sort((a, b) => Math.abs(ClipperLib.Clipper.Area(b)) - Math.abs(ClipperLib.Clipper.Area(a)));
    return normalPath(paths[0]);
}

export function createPlacementValidator(outline, sheet, margin = 0, constraints = 0) {
    if (typeof constraints?.prepare === 'function') return constraints;
    const legacy = typeof constraints === 'number';
    const horizontal = legacy ? constraints : constraints.horizontal ?? 0;
    const vertical = legacy ? constraints : constraints.vertical ?? 0;
    const minimum = legacy ? 0 : constraints.minimumParallel ?? 0;
    const lines = legacy ? [] : constraints.cutLines ?? [];
    const shapes = new Map([0, 90, 180, 270].map(rotation => {
        const raw = outline.map(p => rotate(p, rotation));
        const reserved = legacy ? offset(raw, horizontal / 2) : directionalEnvelope(raw, horizontal, vertical);
        const segments = lines.flatMap(line => {
            const a = rotate({ x: line.x1, y: line.y1 }, rotation), b = rotate({ x: line.x2, y: line.y2 }, rotation);
            const length = Math.hypot(b.x - a.x, b.y - a.y);
            if (length < tolerance) return [];
            let dx = (b.x - a.x) / length, dy = (b.y - a.y) / length;
            if (dx < -1e-8 || (Math.abs(dx) < 1e-8 && dy < 0)) { dx = -dx; dy = -dy; }
            const start = a.x * dx + a.y * dy, end = b.x * dx + b.y * dy;
            const key = `${Math.round(dx * 1e6)}:${Math.round(dy * 1e6)}`;
            return [{ key, dx, dy, normal: -a.x * dy + a.y * dx, start: Math.min(start, end), end: Math.max(start, end) }];
        });
        return [rotation, { raw, reserved, segments, box: bounds(raw), reservedBox: bounds(reserved) }];
    }));

    function prepare(placement) {
        if (![placement.x, placement.y, placement.rotation].every(Number.isFinite) || !shapes.has(placement.rotation)) throw new Error('Invalid placement.');
        const shape = shapes.get(placement.rotation);
        const moveBox = b => ({ left: b.left + placement.x, right: b.right + placement.x, top: b.top + placement.y, bottom: b.bottom + placement.y });
        let reserved, groups;
        return {
            box: moveBox(shape.box), reservedBox: moveBox(shape.reservedBox),
            get reserved() { return reserved ??= shape.reserved.map(p => ({ x: p.x + placement.x, y: p.y + placement.y })); },
            get groups() {
                if (groups) return groups;
                groups = new Map();
                for (const segment of shape.segments) {
                    const along = placement.x * segment.dx + placement.y * segment.dy;
                    const translated = { ...segment, start: segment.start + along, end: segment.end + along, normal: segment.normal - placement.x * segment.dy + placement.y * segment.dx };
                    if (!groups.has(segment.key)) groups.set(segment.key, []);
                    groups.get(segment.key).push(translated);
                }
                return groups;
            },
        };
    }

    function inside(entry) {
        const b = entry.box;
        return b.left >= margin - 0.001 && b.top >= margin - 0.001 && b.right <= sheet.width - margin + 0.001 && b.bottom <= sheet.height - margin + 0.001;
    }

    function translate(entry, x, y) {
        const moveBox = b => ({ left: b.left + x, right: b.right + x, top: b.top + y, bottom: b.bottom + y });
        let reserved, groups;
        return {
            box: moveBox(entry.box), reservedBox: moveBox(entry.reservedBox),
            get reserved() { return reserved ??= entry.reserved.map(p => ({ x: p.x + x, y: p.y + y })); },
            get groups() {
                return groups ??= new Map([...entry.groups].map(([key, segments]) => [key, segments.map(s => {
                    const along = x * s.dx + y * s.dy;
                    return { ...s, start: s.start + along, end: s.end + along, normal: s.normal - x * s.dy + y * s.dx };
                })]));
            },
        };
    }

    function compatible(a, b) {
        if (!separate(a.reservedBox, b.reservedBox) && intersectionArea(a.reserved, b.reserved) > 0.00001) return false;
        if (minimum && !separate(a.box, b.box, minimum)) {
            for (const [key, segments] of a.groups) for (const first of segments) for (const second of b.groups.get(key) ?? []) {
                const normalDistance = Math.abs(first.normal - second.normal);
                if (normalDistance >= minimum - tolerance) continue;
                const alongDistance = Math.max(0, first.start - second.end, second.start - first.end);
                const distance = Math.hypot(normalDistance, alongDistance);
                if (distance > tolerance && distance < minimum - tolerance) return false;
            }
        }
        return true;
    }

    function validate(placements) {
        const entries = [];
        for (const placement of placements) {
            const entry = prepare(placement);
            if (!inside(entry)) throw new Error('A dieline crosses the sheet margin.');
            if (entries.some(other => !compatible(entry, other))) throw new Error('Dielines overlap or do not meet the required spacing or parallel cut-line distance.');
            entries.push(entry);
        }
    }

    function* contacts(rotation, entries) {
        const groups = new Map();
        for (const segment of shapes.get(rotation).segments) {
            if (!groups.has(segment.key)) groups.set(segment.key, []);
            groups.get(segment.key).push(segment);
        }
        const longest = segments => [...segments].sort((a, b) => (b.end - b.start) - (a.end - a.start)).slice(0, 8);
        for (const [key, segments] of groups) for (const source of longest(segments)) for (const entry of entries) for (const target of longest(entry.groups.get(key) ?? [])) {
            for (const along of [target.start - source.start, target.end - source.end]) for (const clearance of new Set([0, minimum, -minimum])) {
                const normal = target.normal - source.normal + clearance;
                yield { x: along * source.dx - normal * source.dy, y: along * source.dy + normal * source.dx, rotation };
            }
        }
    }

    const zeroSpacing = horizontal === 0 && vertical === 0 && minimum === 0;
    return { prepare, translate, inside, compatible, validate, contacts, hasContacts: lines.length > 0 && !zeroSpacing, zeroSpacing };
}
