import { test } from 'node:test';
import assert from 'node:assert/strict';
import { compactPlacements, nest, validatePlacements } from '../../resources/js/dielines/nest.mjs';
import { extractOutline } from '../../resources/js/dielines/outline.mjs';

const polygon = [{ x: 0, y: 0 }, { x: 100, y: 0 }, { x: 100, y: 30 }, { x: 30, y: 30 }, { x: 30, y: 100 }, { x: 0, y: 100 }];
const geometry = { layers: { cut: polygon.map((p, i) => ({ x1: p.x, y1: p.y, x2: polygon[(i + 1) % polygon.length].x, y2: polygon[(i + 1) % polygon.length].y })) } };

const transform = (points, p) => points.map(q => ({ x: q.x * Math.cos(p.rotation * Math.PI / 180) - q.y * Math.sin(p.rotation * Math.PI / 180) + p.x, y: q.x * Math.sin(p.rotation * Math.PI / 180) + q.y * Math.cos(p.rotation * Math.PI / 180) + p.y }));
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
