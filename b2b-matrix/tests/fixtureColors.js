// Test-only: expands the mock matrix to many colours/sizes for sticky-header checks (?manycolors).
export function manyColors(base, colorCount = 14) {
  const sizes = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL'];
  const rows = Object.values(base.matrix), colors = [], matrix = {};
  let id = 5000;
  for (let i = 0; i < colorCount; i++) {
    const name = base.colors[i % base.colors.length] + (i >= base.colors.length ? ' ' + (i + 1) : '');
    const row = rows[i % rows.length], first = Object.values(row)[0];
    colors.push(name);
    matrix[name] = Object.fromEntries(sizes.map(size => [size, { id: id++, price: (row[size] || first).price, img: first.img }]));
  }
  return { ...structuredClone(base), colors, sizes, matrix, existing_quantities: {} };
}
