export function rootOptions(items) {
  const roots = new Map();
  for (const item of items) for (const root of item.root_filters || []) roots.set(String(root.id), root);
  return [...roots.values()].sort((a, b) => a.name.localeCompare(b.name, 'el'));
}

export function categoryOptions(items, rootId = '') {
  const categories = new Map();
  for (const item of items) {
    for (const category of item.category_filters || []) {
      if (!rootId || String(category.root_id) === String(rootId)) categories.set(String(category.id), category);
    }
  }
  return [...categories.values()].sort((a, b) => `${a.root_name} ${a.name}`.localeCompare(`${b.root_name} ${b.name}`, 'el'));
}

export function filterListItems(items, rootId = '', categoryId = '', query = '') {
  const search = query.trim().toLocaleLowerCase('el');
  return items.filter(item => {
    if (rootId && !(item.root_filters || []).some(root => String(root.id) === String(rootId))) return false;
    if (categoryId && !(item.category_filters || []).some(category => String(category.id) === String(categoryId))) return false;
    return !search || `${item.sku || ''} ${item.name || ''} ${item.brand_name || ''} ${(item.category_filters || []).map(category => category.name).join(' ')}`.toLocaleLowerCase('el').includes(search);
  });
}

export function listTotals(items) {
  return items.reduce((total, item) => ({
    qty: total.qty + Number(item.total_qty || 0),
    value: total.value + Number(item.total_val || 0),
    unpriced: total.unpriced || Boolean(item.unpriced),
  }), { qty: 0, value: 0, unpriced: false });
}
