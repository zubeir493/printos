import { test } from 'node:test';
import assert from 'node:assert/strict';
import { compactPlacements, nest, sheetPlacementRegion, validatePlacements } from '../../resources/js/dielines/nest.mjs';
import { extractOutline, offset } from '../../resources/js/dielines/outline.mjs';

const polygon = [{ x: 0, y: 0 }, { x: 100, y: 0 }, { x: 100, y: 30 }, { x: 30, y: 30 }, { x: 30, y: 100 }, { x: 0, y: 100 }];
const geometry = { layers: { cut: polygon.map((p, i) => ({ x1: p.x, y1: p.y, x2: polygon[(i + 1) % polygon.length].x, y2: polygon[(i + 1) % polygon.length].y })) } };

const transform = (points, p) => points.map(q => ({ x: q.x * Math.cos(p.rotation * Math.PI / 180) - q.y * Math.sin(p.rotation * Math.PI / 180) + p.x, y: q.x * Math.sin(p.rotation * Math.PI / 180) + q.y * Math.cos(p.rotation * Math.PI / 180) + p.y }));
const sourceFor = points => ({ layers: { cut: points.map((p, i) => ({ x1: p.x, y1: p.y, x2: points[(i + 1) % points.length].x, y2: points[(i + 1) % points.length].y })) } });
const rectangle = [{ x: 0, y: 0 }, { x: 40, y: 0 }, { x: 40, y: 30 }, { x: 0, y: 30 }];

test('sheet placement limits ignore the spacing envelope on every edge and rotation', () => {
    const outline = rectangle.map(p => ({ x: p.x + 12, y: p.y - 7 }));
    for (const spacing of [0, 5, 40]) for (const rotation of [0, 90, 180, 270]) for (const margin of [0, 4]) {
        const expanded = offset(outline, spacing / 2 + 0.15), sheet = { width: 100, height: 90 };
        const anchor = transform(expanded, { x: 0, y: 0, rotation })[0];
        const region = sheetPlacementRegion(outline, expanded, rotation, sheet, margin);
        assert.equal(region.length, 1);
        for (const point of region[0]) {
            const raw = transform(outline, { x: point.x - anchor.x, y: point.y - anchor.y, rotation });
            const xs = raw.map(p => p.x), ys = raw.map(p => p.y);
            const left = Math.min(...xs), top = Math.min(...ys), right = Math.max(...xs), bottom = Math.max(...ys);
            assert.ok(Math.abs(left - margin) < 1e-6 || Math.abs(right - sheet.width + margin) < 1e-6);
            assert.ok(Math.abs(top - margin) < 1e-6 || Math.abs(bottom - sheet.height + margin) < 1e-6);
        }
    }
});

test('fits spaced interlocking outlines against the sheet edges without reserving an outer gap', () => {
    const sheet = { width: 141, height: 141 };
    const result = nest({ geometry, sheet, horizontal_gap: 10, vertical_gap: 6, minimum_parallel_clearance: 3, max_copies: 2, search_ms: 500 });
    assert.equal(result.copies, 2);
    const points = result.placements.flatMap(p => transform(polygon, p));
    assert.ok(Math.abs(Math.min(...points.map(p => p.x))) < 0.0001);
    assert.ok(Math.abs(Math.min(...points.map(p => p.y))) < 0.0001);
    validatePlacements(result.outline, result.placements, sheet, 0, { horizontal: 10, vertical: 6, minimumParallel: 3, cutLines: geometry.layers.cut });
});
function boundaryDistance(a, b) {
    let minimum = Infinity;
    for (const [points, edges] of [[a, b], [b, a]]) for (const point of points) for (let i = 0; i < edges.length; i++) {
        const start = edges[i], end = edges[(i + 1) % edges.length], dx = end.x - start.x, dy = end.y - start.y;
        const t = Math.max(0, Math.min(1, ((point.x - start.x) * dx + (point.y - start.y) * dy) / (dx * dx + dy * dy)));
        minimum = Math.min(minimum, Math.hypot(point.x - start.x - t * dx, point.y - start.y - t * dy));
    }
    return minimum;
}

test('fills an exact-size sheet with cut edges touching each other and all paper edges', () => {
    const rectangle = [{ x: 0, y: 0 }, { x: 50, y: 0 }, { x: 50, y: 40 }, { x: 0, y: 40 }];
    const source = { layers: { cut: rectangle.map((p, i) => ({ x1: p.x, y1: p.y, x2: rectangle[(i + 1) % 4].x, y2: rectangle[(i + 1) % 4].y })) } };
    const result = nest({ geometry: source, sheet: { width: 100, height: 80 }, search_ms: 500 });
    assert.equal(result.copies, 4);
    const copies = result.placements.map(p => transform(rectangle, p)), points = copies.flat();
    assert.ok(Math.abs(Math.min(...points.map(p => p.x))) < 0.0001);
    assert.ok(Math.abs(Math.min(...points.map(p => p.y))) < 0.0001);
    assert.ok(Math.abs(Math.max(...points.map(p => p.x)) - 100) < 0.0001);
    assert.ok(Math.abs(Math.max(...points.map(p => p.y)) - 80) < 0.0001);
    for (let i = 0; i < copies.length; i++) assert.ok(copies.some((other, j) => i !== j && boundaryDistance(copies[i], other) < 0.0001));
    validatePlacements(result.outline, result.placements, { width: 100, height: 80 }, 0, 0);
});

test('interlocks true concave cut outlines with zero clearance', () => {
    const result = nest({ geometry, sheet: { width: 140, height: 140 }, search_ms: 1500 });
    assert.ok(result.copies >= 2);
    const copies = result.placements.map(p => transform(polygon, p));
    assert.ok(copies.some((a, i) => copies.some((b, j) => i !== j && boundaryDistance(a, b) < 0.0002)));
    validatePlacements(result.outline, result.placements, { width: 140, height: 140 }, 0, 0);
});

test('zero clearance permits touching but rejects even a small actual overlap', () => {
    validatePlacements(polygon, [{ x: 0, y: 0, rotation: 0 }, { x: 100, y: 0, rotation: 0 }], { width: 200, height: 100 }, 0, 0);
    assert.throws(() => validatePlacements(polygon, [{ x: 0, y: 0, rotation: 0 }, { x: 99.999, y: 0, rotation: 0 }], { width: 200, height: 100 }, 0, 0), /overlap/);
});

test('removes the real gap between slanted cut edges even when their bounding boxes touch', () => {
    const slanted = [{ x: 0, y: 0 }, { x: 60, y: 0 }, { x: 100, y: 100 }, { x: 40, y: 100 }];
    const placements = compactPlacements(slanted, [{ x: 0, y: 0, rotation: 0 }, { x: 100, y: 0, rotation: 0 }], { width: 200, height: 100 });
    assert.ok(Math.abs(placements[1].x - 60) < 0.0001);
    assert.ok(boundaryDistance(transform(slanted, placements[0]), transform(slanted, placements[1])) < 0.0001);
});

test('fills the remaining strip with extra copies in a different rotation', () => {
    const rectangle = [{ x: 0, y: 0 }, { x: 30, y: 0 }, { x: 30, y: 60 }, { x: 0, y: 60 }];
    const source = { layers: { cut: rectangle.map((p, i) => ({ x1: p.x, y1: p.y, x2: rectangle[(i + 1) % 4].x, y2: rectangle[(i + 1) % 4].y })) } };
    const result = nest({ geometry: source, sheet: { width: 100, height: 100 }, search_ms: 1500 });
    assert.equal(result.copies, 4);
    assert.ok(new Set(result.placements.map(p => p.rotation % 180)).size > 1);
    validatePlacements(result.outline, result.placements, { width: 100, height: 100 }, 0, 0);
});

test('interlocks concave shapes where a bounding-box grid fits only one', () => {
    const result = nest({ geometry, sheet: { width: 140, height: 140 }, margin: 2, gap: 3, search_ms: 1500 });
    assert.ok(result.copies >= 2, `Expected at least 2 interlocked shapes; got ${result.copies}`);
    validatePlacements(result.outline, result.placements, { width: 140, height: 140 }, 2, 3);
});

test('rejects overlaps, insufficient spacing and sheet overflow', () => {
    const outline = polygon, sheet = { width: 300, height: 300 };
    assert.throws(() => validatePlacements(outline, [{ x: 5, y: 5, rotation: 0 }, { x: 6, y: 6, rotation: 0 }], sheet, 5, 3), /overlap/);
    assert.throws(() => validatePlacements(outline, [{ x: 5, y: 5, rotation: 0 }, { x: 106, y: 5, rotation: 0 }], sheet, 5, 3), /spacing/);
    assert.throws(() => validatePlacements(outline, [{ x: 250, y: 5, rotation: 0 }], sheet, 5, 3), /margin/);
});

test('rejects disconnected outlines rather than silently dropping a component', () => {
    const detached = { layers: { cut: [...geometry.layers.cut, ...geometry.layers.cut.map(l => ({ x1: l.x1 + 300, y1: l.y1, x2: l.x2 + 300, y2: l.y2 }))] } };
    assert.throws(() => extractOutline(detached), /outline|disconnected/);
});

test('prefers a complete grid to an equally dense irregular layout', () => {
    const result = nest({ geometry: sourceFor(rectangle), sheet: { width: 130, height: 100 }, horizontal_gap: 0, vertical_gap: 0, minimum_parallel_clearance: 3, search_ms: 300 });
    assert.equal(result.copies, 9);
    assert.deepEqual(result.placements, Array.from({ length: 9 }, (_, i) => ({ x: i % 3 * 40, y: Math.floor(i / 3) * 30, rotation: 0 })));
});

test('keeps a rotated remainder strip in orderly rows instead of scattering copies', () => {
    const points = [{ x: 0, y: 0 }, { x: 30, y: 0 }, { x: 30, y: 60 }, { x: 0, y: 60 }];
    const result = nest({ geometry: sourceFor(points), sheet: { width: 100, height: 100 }, horizontal_gap: 0, vertical_gap: 0, minimum_parallel_clearance: 3, search_ms: 300 });
    assert.equal(result.copies, 4);
    assert.deepEqual(result.placements.slice(0, 3), [0, 30, 60].map(x => ({ x, y: 0, rotation: 0 })));
    assert.equal(result.placements[3].y, 60);
});

test('horizontal and vertical spacing remain independent in sheet coordinates', () => {
    for (const [horizontal, vertical, sheet, expected] of [
        [5, 0, { width: 130, height: 60 }, 6],
        [0, 5, { width: 120, height: 65 }, 6],
    ]) {
        const source = sourceFor(rectangle);
        const result = nest({ geometry: source, sheet, horizontal_gap: horizontal, vertical_gap: vertical, minimum_parallel_clearance: 3, search_ms: 300 });
        assert.equal(result.copies, expected);
        const positions = result.placements.map(p => transform(rectangle, p));
        const boxes = positions.map(points => ({ x: Math.min(...points.map(p => p.x)), y: Math.min(...points.map(p => p.y)), width: Math.max(...points.map(p => p.x)) - Math.min(...points.map(p => p.x)), height: Math.max(...points.map(p => p.y)) - Math.min(...points.map(p => p.y)) }));
        for (const a of boxes) for (const b of boxes) {
            if (a === b) continue;
            if (Math.min(a.y + a.height, b.y + b.height) > Math.max(a.y, b.y) + 0.001) assert.ok(Math.max(b.x - a.x - a.width, a.x - b.x - b.width) >= horizontal - 0.001);
            if (Math.min(a.x + a.width, b.x + b.width) > Math.max(a.x, b.x) + 0.001) assert.ok(Math.max(b.y - a.y - a.height, a.y - b.y - b.height) >= vertical - 0.001);
        }
    }
    const constraints = { horizontal: 5, vertical: 0, minimumParallel: 0 };
    validatePlacements(rectangle, [{ x: 30, y: 0, rotation: 90 }, { x: 65, y: 0, rotation: 90 }], { width: 65, height: 40 }, 0, constraints);
    assert.throws(() => validatePlacements(rectangle, [{ x: 30, y: 0, rotation: 90 }, { x: 64, y: 0, rotation: 90 }], { width: 65, height: 40 }, 0, constraints), /spacing/);
});

test('shared cuts are allowed while separate parallel cuts must meet the minimum', () => {
    const source = sourceFor(rectangle), sheet = { width: 100, height: 100 };
    const constraints = { horizontal: 0, vertical: 0, minimumParallel: 3, cutLines: source.layers.cut };
    for (const x of [40, 43]) validatePlacements(rectangle, [{ x: 0, y: 0, rotation: 0 }, { x, y: 0, rotation: 0 }], sheet, 0, constraints);
    for (const x of [40.01, 41, 42.99]) assert.throws(() => validatePlacements(rectangle, [{ x: 0, y: 0, rotation: 0 }, { x, y: 0, rotation: 0 }], sheet, 0, constraints), /parallel/);
    validatePlacements(rectangle, [{ x: 0, y: 0, rotation: 0 }, { x: 41, y: 0, rotation: 0 }], sheet, 0, { ...constraints, minimumParallel: 1 });
    validatePlacements(rectangle, [{ x: 30, y: 0, rotation: 90 }, { x: 63, y: 0, rotation: 90 }], sheet, 0, constraints);
    assert.throws(() => validatePlacements(rectangle, [{ x: 30, y: 0, rotation: 90 }, { x: 62, y: 0, rotation: 90 }], sheet, 0, constraints), /parallel/);
    for (const [x, y] of [[42, 30], [41, 31]]) assert.throws(() => validatePlacements(rectangle, [{ x: 0, y: 0, rotation: 0 }, { x, y, rotation: 0 }], sheet, 0, constraints), /parallel/);
    validatePlacements(rectangle, [{ x: 0, y: 0, rotation: 0 }, { x: 43, y: 33, rotation: 0 }], sheet, 0, constraints);
    const result = nest({ geometry: source, sheet: { width: 126, height: 60 }, horizontal_gap: 1, vertical_gap: 0, minimum_parallel_clearance: 3, search_ms: 300 });
    assert.equal(result.copies, 6);
    assert.deepEqual(result.placements.map(p => p.x), [0, 43, 86, 0, 43, 86]);
});

test('checks slanted parallel cuts by perpendicular distance and allows exact common lines', () => {
    const slanted = [{ x: 0, y: 0 }, { x: 60, y: 0 }, { x: 100, y: 100 }, { x: 40, y: 100 }];
    const constraints = { horizontal: 0, vertical: 0, minimumParallel: 3, cutLines: sourceFor(slanted).layers.cut };
    const sheet = { width: 250, height: 110 };
    validatePlacements(slanted, [{ x: 0, y: 0, rotation: 0 }, { x: 60, y: 0, rotation: 0 }], sheet, 0, constraints);
    assert.throws(() => validatePlacements(slanted, [{ x: 0, y: 0, rotation: 0 }, { x: 62, y: 0, rotation: 0 }], sheet, 0, constraints), /parallel/);
    validatePlacements(slanted, [{ x: 0, y: 0, rotation: 0 }, { x: 64, y: 0, rotation: 0 }], sheet, 0, constraints);
    const result = nest({ geometry: sourceFor(slanted), sheet: { width: 160, height: 100 }, horizontal_gap: 0, vertical_gap: 0, minimum_parallel_clearance: 3, search_ms: 500 });
    assert.equal(result.copies, 2);
    validatePlacements(slanted, result.placements, { width: 160, height: 100 }, 0, constraints);
});

test('a shared exterior edge does not exempt nearby internal cut lines', () => {
    const cuts = [...sourceFor(rectangle).layers.cut, { x1: 39, y1: 5, x2: 39, y2: 25 }];
    const constraints = { horizontal: 0, vertical: 0, minimumParallel: 3, cutLines: cuts };
    assert.throws(() => validatePlacements(rectangle, [{ x: 0, y: 0, rotation: 0 }, { x: 40, y: 0, rotation: 0 }], { width: 100, height: 100 }, 0, constraints), /parallel/);
    const result = nest({ geometry: { layers: { cut: cuts } }, sheet: { width: 126, height: 60 }, horizontal_gap: 0, vertical_gap: 0, minimum_parallel_clearance: 3, search_ms: 300 });
    assert.equal(result.copies, 6);
    assert.deepEqual(result.placements.map(p => p.x), [0, 43, 86, 0, 43, 86]);
});
