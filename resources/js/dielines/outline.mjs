import ClipperLib from 'clipper-lib';

const scale = 10000;
const cross = (a, b) => a.x * b.y - a.y * b.x;
const subtract = (a, b) => ({ x: a.x - b.x, y: a.y - b.y });
const distance = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);

export const integerPath = polygon => polygon.map(p => ({ X: Math.round(p.x * scale), Y: Math.round(p.y * scale) }));
export const normalPath = polygon => polygon.map(p => ({ x: p.X / scale, y: p.Y / scale }));
export const area = polygon => Math.abs(ClipperLib.Clipper.Area(integerPath(polygon))) / scale ** 2;

function segmentDistance(p, a, b) {
    const d = subtract(b, a);
    const t = Math.max(0, Math.min(1, ((p.x - a.x) * d.x + (p.y - a.y) * d.y) / (d.x ** 2 + d.y ** 2 || 1)));
    return distance(p, { x: a.x + t * d.x, y: a.y + t * d.y });
}

export function contains(polygon, point, tolerance = 0.025) {
    if (ClipperLib.Clipper.PointInPolygon(integerPath([point])[0], integerPath(polygon)) !== 0) return true;
    return polygon.some((p, i) => segmentDistance(point, p, polygon[(i + 1) % polygon.length]) <= tolerance);
}

/** Trace the exterior face of the cut/fold graph; slots and vents remain inside it. */
export function extractOutline(geometry) {
    const lines = ['cut', 'crease', 'cut_crease', 'perforation'].flatMap(layer => geometry.layers[layer] ?? []);
    const segments = lines.map(l => ({ a: { x: l.x1, y: l.y1 }, b: { x: l.x2, y: l.y2 }, splits: [0, 1] })).filter(s => distance(s.a, s.b) > 0.0001);
    for (let i = 0; i < segments.length; i++) {
        const a = segments[i], r = subtract(a.b, a.a);
        for (let j = i + 1; j < segments.length; j++) {
            const b = segments[j], s = subtract(b.b, b.a), delta = subtract(b.a, a.a), denominator = cross(r, s);
            if (Math.abs(denominator) > 1e-8) {
                const t = cross(delta, s) / denominator, u = cross(delta, r) / denominator;
                if (t >= -1e-6 && t <= 1.000001 && u >= -1e-6 && u <= 1.000001) {
                    a.splits.push(Math.max(0, Math.min(1, t)));
                    b.splits.push(Math.max(0, Math.min(1, u)));
                }
            }
            for (const [target, point] of [[a, b.a], [a, b.b], [b, a.a], [b, a.b]]) {
                const d = subtract(target.b, target.a);
                const t = ((point.x - target.a.x) * d.x + (point.y - target.a.y) * d.y) / (d.x ** 2 + d.y ** 2);
                if (t > 0 && t < 1 && segmentDistance(point, target.a, target.b) < 0.005) target.splits.push(t);
            }
        }
    }
    const nodes = [], buckets = new Map();
    function node(point) {
        const x = Math.floor(point.x / 0.01), y = Math.floor(point.y / 0.01);
        for (let dx = -1; dx <= 1; dx++) for (let dy = -1; dy <= 1; dy++) {
            for (const id of buckets.get(`${x + dx},${y + dy}`) ?? []) if (distance(nodes[id], point) < 0.005) return id;
        }
        const id = nodes.length;
        nodes.push({ ...point, neighbours: new Set() });
        const key = `${x},${y}`;
        buckets.set(key, [...(buckets.get(key) ?? []), id]);
        return id;
    }
    for (const segment of segments) {
        const splits = [...new Set(segment.splits)].sort((a, b) => a - b);
        let previous = null;
        for (const t of splits) {
            const id = node({ x: segment.a.x + (segment.b.x - segment.a.x) * t, y: segment.a.y + (segment.b.y - segment.a.y) * t });
            if (previous !== null && previous !== id) { nodes[previous].neighbours.add(id); nodes[id].neighbours.add(previous); }
            previous = id;
        }
    }
    for (const p of nodes) p.neighbours = [...p.neighbours].sort((a, b) => Math.atan2(nodes[a].y - p.y, nodes[a].x - p.x) - Math.atan2(nodes[b].y - p.y, nodes[b].x - p.x));
    const visited = new Set(), faces = [];
    for (let start = 0; start < nodes.length; start++) for (const next of nodes[start].neighbours) {
        if (visited.has(`${start}:${next}`)) continue;
        const face = [];
        let a = start, b = next;
        for (let limit = 0; limit < segments.length * 8; limit++) {
            const key = `${a}:${b}`;
            if (visited.has(key)) break;
            visited.add(key); face.push({ x: nodes[a].x, y: nodes[a].y });
            const neighbours = nodes[b].neighbours, index = neighbours.indexOf(a);
            [a, b] = [b, neighbours[(index + neighbours.length - 1) % neighbours.length]];
            if (a === start && b === next) break;
        }
        if (face.length >= 3) faces.push(face);
    }
    faces.sort((a, b) => area(b) - area(a));
    const outer = faces[0];
    if (!outer || area(outer) < 1 || !segments.every(s => contains(outer, s.a) && contains(outer, s.b))) {
        throw new Error('A closed outer outline could not be resolved for this dieline.');
    }
    const simplified = ClipperLib.Clipper.SimplifyPolygon(integerPath(outer), ClipperLib.PolyFillType.pftNonZero);
    simplified.sort((a, b) => Math.abs(ClipperLib.Clipper.Area(b)) - Math.abs(ClipperLib.Clipper.Area(a)));
    const exterior = normalPath(simplified[0]);
    const openSegments = segments.filter(s => !contains(exterior, s.a, 0.0001) || !contains(exterior, s.b, 0.0001));
    if (!openSegments.length) return exterior;
    // Reserve open slits/receiver tongues too: simplification can remove an
    // out-and-back edge even though the original cutter line must stay clear.
    const strokes = new ClipperLib.ClipperOffset(2, 1), stroked = [];
    strokes.AddPaths(openSegments.map(s => integerPath([s.a, s.b])), ClipperLib.JoinType.jtMiter, ClipperLib.EndType.etOpenSquare);
    strokes.Execute(stroked, 1);
    const union = new ClipperLib.Clipper(), combined = [];
    union.AddPaths([simplified[0], ...stroked], ClipperLib.PolyType.ptSubject, true);
    union.Execute(ClipperLib.ClipType.ctUnion, combined, ClipperLib.PolyFillType.pftNonZero, ClipperLib.PolyFillType.pftNonZero);
    combined.sort((a, b) => Math.abs(ClipperLib.Clipper.Area(b)) - Math.abs(ClipperLib.Clipper.Area(a)));
    const result = normalPath(combined[0]);
    if (!segments.every(s => contains(result, s.a) && contains(result, s.b))) throw new Error('The dieline has disconnected outer components.');
    return result;
}

export function offset(polygon, distance) {
    if (distance === 0) return polygon.map(p => ({ ...p }));
    const input = integerPath(polygon);
    if (!ClipperLib.Clipper.Orientation(input)) input.reverse();
    const offsetter = new ClipperLib.ClipperOffset(2, 0.02 * scale);
    offsetter.AddPath(input, ClipperLib.JoinType.jtRound, ClipperLib.EndType.etClosedPolygon);
    const paths = [];
    offsetter.Execute(paths, distance * scale);
    paths.sort((a, b) => Math.abs(ClipperLib.Clipper.Area(b)) - Math.abs(ClipperLib.Clipper.Area(a)));
    return normalPath(paths[0]);
}

export function intersectionArea(a, b) {
    const clipper = new ClipperLib.Clipper(), paths = [];
    clipper.AddPath(integerPath(a), ClipperLib.PolyType.ptSubject, true);
    clipper.AddPath(integerPath(b), ClipperLib.PolyType.ptClip, true);
    clipper.Execute(ClipperLib.ClipType.ctIntersection, paths, ClipperLib.PolyFillType.pftNonZero, ClipperLib.PolyFillType.pftNonZero);
    return paths.reduce((sum, p) => sum + Math.abs(ClipperLib.Clipper.Area(p)), 0) / scale ** 2;
}
