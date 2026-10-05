// One transport, no automatic replay of a write after an uncertain response.
export function createListApi(endpoint, fetcher = fetch, origin = window.location.origin) {
  const url = new URL(endpoint, origin);
  if (url.origin !== origin) throw new Error('Μη έγκυρη διεύθυνση σύνδεσης.');
  let userId = null;
  const actions = { 'list-data': 'b2b_get_preorder_list_data', 'search-products': 'b2b_search_products', empty: 'b2b_empty_custom_list', 'remove-parent': 'b2b_remove_parent_custom', 'start-order': 'b2b_start_order', 'add-batch': 'b2b_add_order_batch', 'finalize-order': 'b2b_finalize_order', 'order-status': 'b2b_order_process_status', 'cancel-order': 'b2b_cancel_order', 'acknowledge-removals': 'b2b_acknowledge_removed_items' };
  async function request(action, params = {}, write = false, signal) {
    const target = new URL(url);
    const fields = new URLSearchParams({ action, ...params });
    const options = { credentials: 'same-origin', cache: 'no-store', signal: signal || AbortSignal.timeout(action === 'b2b_finalize_order' ? 180000 : 45000) };
    if (write) { options.method = 'POST'; options.body = fields; }
    else fields.forEach((value, key) => target.searchParams.set(key, value));
    const response = await fetcher(target, options);
    let body;
    try { body = await response.json(); } catch { throw new Error('Μη έγκυρη απάντηση από το κατάστημα. Δοκιμάστε ξανά.'); }
    if (!response.ok || body.success !== true) {
      const error = new Error(body.data?.message || 'Η ενέργεια δεν επιβεβαιώθηκε. Δοκιμάστε ξανά.');
      error.status = response.status; error.code = body.data?.code; throw error;
    }
    return body.data;
  }
  return {
    async read(endpoint, params = {}, signal) {
      const data = await request(actions[endpoint], params, false, signal);
      if (endpoint === 'list-data') {
        if (!Number.isInteger(data?.user_id) || data.user_id <= 0) throw new Error('Δεν επιβεβαιώθηκε ο λογαριασμός.');
        userId = data.user_id;
      }
      return data;
    },
    async write(endpoint, params = {}) {
      const session = await request('b2b_matrix_session');
      if (!userId || session?.user_id !== userId || !session?.nonce) {
        const error = new Error('Ο λογαριασμός άλλαξε. Ανανεώστε τη λίστα πριν συνεχίσετε.'); error.code = 'account_changed'; throw error;
      }
      return request(actions[endpoint], { ...params, security: session.nonce, expected_user_id: userId }, true);
    },
  };
}
