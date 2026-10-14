<?php
return [
    'draft survives list reload and isolates accounts without reading all quantities' => function() {
        $draft = B2B_Matrix_DB::get_draft(7);
        $saved = B2B_Matrix_DB::save_draft(7, "Από το κατάστημα\nΠαράδοση στο σπίτι", $draft['revision']);
        check(!is_wp_error($saved) && $saved['note'] === "Από το κατάστημα\nΠαράδοση στο σπίτι");
        check(B2B_Matrix_DB::get_draft(12)['note'] === '');
        $reads = $GLOBALS['full_list_reads'];
        $r = B2B_Preorder_List::dispatch('draft-note', []);
        check($r['data']['user_id'] === 7 && $r['data']['draft'] === $saved && $GLOBALS['full_list_reads'] === $reads);
        check(B2B_Preorder_List::get_list_payload(7)['draft'] === $saved);
    },
    'stale device cannot overwrite newer note or resurrect cleared note' => function() {
        $first = B2B_Matrix_DB::get_draft(7);
        $saved = B2B_Matrix_DB::save_draft(7, 'Νεότερο', $first['revision']);
        error_is(B2B_Matrix_DB::save_draft(7, 'Παλιό', $first['revision']), 'note_conflict');
        check(B2B_Matrix_DB::get_draft(7) === $saved);
        check(B2B_Matrix_DB::clear_user_items(7) === true);
        check(B2B_Matrix_DB::get_draft(7)['note'] === '');
        error_is(B2B_Matrix_DB::save_draft(7, 'Νεότερο', $saved['revision']), 'note_conflict');
    },
    'note validation accepts 4000 Greek characters and rejects invalid UTF8 or oversized input' => function() {
        $revision = B2B_Matrix_DB::get_draft(7)['revision'];
        $saved = B2B_Matrix_DB::save_draft(7, str_repeat('Ω',4000), $revision);
        check($saved['note'] === str_repeat('Ω',4000));
        foreach ([str_repeat('Ω',4001), "\xC3\x28", ['bad'], null] as $bad) error_is(B2B_Matrix_DB::save_draft(7,$bad,$saved['revision']), 'invalid_note');
        foreach (['< 40 τεμ.', '5<10 & >2', 'Έκπτωση 10%20', '<b>Σχόλιο</b>', "Γραμμή 1\nΓραμμή 2"] as $text) check(B2B_Matrix_DB::sanitize_note($text) === $text);
        check(B2B_Matrix_DB::sanitize_note("Α\r\nΒ\x07\x00Γ") === "Α\nΒΓ");
        $plain = B2B_Matrix_DB::save_draft(7, '< 40 τεμ. ανά χρώμα', $saved['revision']);
        check($plain['note'] === '< 40 τεμ. ανά χρώμα' && B2B_Matrix_DB::get_draft(7)['note'] === '< 40 τεμ. ανά χρώμα');
    },
    'drafts stored by older versions show "<" instead of "&lt;"' => function() {
        global $wpdb;
        $revision = B2B_Matrix_DB::get_draft(7)['revision'];
        B2B_Matrix_DB::save_draft(7, '&lt; 40 τεμ.', $revision);
        check(B2B_Matrix_DB::get_draft(7)['note'] === '< 40 τεμ.');
    },
    'draft writes require current account, nonce and permission' => function() {
        $revision = B2B_Matrix_DB::get_draft(7)['revision'];
        error_is(B2B_Preorder_List::dispatch('save-note', ['customer_note'=>'x','note_revision'=>$revision,'expected_user_id'=>12]), 'account_changed');
        $_POST = ['customer_note'=>'x','note_revision'=>$revision,'expected_user_id'=>7,'security'=>'bad'];
        try { B2B_Preorder_List::ajax_dispatch('save-note'); } catch (JsonExit $e) { check($e->status === 403); }
        check(B2B_Matrix_DB::get_draft(7)['note'] === '');
        $GLOBALS['access'] = false;
        error_is(B2B_Preorder_List::dispatch('save-note', ['customer_note'=>'x','note_revision'=>$revision,'expected_user_id'=>7]), 'b2b_forbidden');
    },
    'server note revision is rechecked at start and exact stored note is frozen in WooCommerce' => function() {
        unset($GLOBALS['wpdb']->items[7][55]);
        $draft = B2B_Matrix_DB::get_draft(7);
        $saved = B2B_Matrix_DB::save_draft(7, str_repeat('Ω',4000), $draft['revision']);
        error_is(B2B_Preorder_List::execute_start_order(7,'ignored',null,null,$draft['revision']), 'note_conflict');
        check(!$GLOBALS['wpdb']->batches && !$GLOBALS['orders']);
        $start = B2B_Preorder_List::execute_start_order(7,'ignored',null,null,$saved['revision']);
        check($GLOBALS['orders'][$start['order_id']]->customer_note === $saved['note']);
        error_is(B2B_Matrix_DB::save_draft(7,'Change during submission',$saved['revision']), 'submission_active');
        B2B_Preorder_List::execute_add_batch(7,$start['order_id'],$start['token']);
        $GLOBALS['fail_draft_write'] = true;
        error_is(B2B_Preorder_List::execute_finalize_order(7,$start['order_id'],$start['token']), 'database_error');
        check(B2B_Matrix_DB::get_draft(7) === $saved && count($GLOBALS['wpdb']->items[7]) === 2);
        $GLOBALS['fail_draft_write'] = false;
        $done = B2B_Preorder_List::execute_finalize_order(7,$start['order_id'],$start['token']);
        check($done['complete'] && B2B_Matrix_DB::get_draft(7)['note'] === '');
        check($GLOBALS['orders'][$start['order_id']]->customer_note === $saved['note']);
        $fresh = B2B_Matrix_DB::get_draft(7);
        B2B_Matrix_DB::save_draft(7,'Επόμενη παραγγελία',$fresh['revision']);
        B2B_Preorder_List::execute_finalize_order(7,$start['order_id'],$start['token']);
        check(B2B_Matrix_DB::get_draft(7)['note'] === 'Επόμενη παραγγελία');
    },
    'empty list and draft are atomic when draft write fails' => function() {
        $draft = B2B_Matrix_DB::get_draft(7);
        $saved = B2B_Matrix_DB::save_draft(7,'Να διατηρηθεί',$draft['revision']);
        $items = $GLOBALS['wpdb']->items;
        $GLOBALS['fail_draft_write'] = true;
        error_is(B2B_Matrix_DB::clear_user_items(7), 'database_error');
        check($GLOBALS['wpdb']->items === $items && B2B_Matrix_DB::get_draft(7) === $saved);
    },
    'read and write failures are never acknowledged as saved drafts' => function() {
        $revision = B2B_Matrix_DB::get_draft(7)['revision'];
        $GLOBALS['fail_draft_read'] = true;
        error_is(B2B_Preorder_List::dispatch('draft-note', []), 'database_error');
        error_is(B2B_Matrix_DB::save_draft(7,'Σχόλιο',$revision), 'database_error');
        $GLOBALS['fail_draft_read'] = false; $GLOBALS['wpdb']->last_error = '';
        $GLOBALS['fail_draft_write'] = true;
        error_is(B2B_Matrix_DB::save_draft(7,'Σχόλιο',$revision), 'database_error');
        check(B2B_Matrix_DB::get_draft(7)['note'] === '');
    },
    'season reset epoch invalidates drafts even when tables are empty' => function() {
        $old = B2B_Matrix_DB::get_draft(7)['revision'];
        $GLOBALS['options']['b2b_matrix_draft_epoch'] = 'new-season';
        error_is(B2B_Matrix_DB::save_draft(7,'Παλιό',$old), 'note_conflict');
    },
    'cancelled submission keeps draft for editing' => function() {
        unset($GLOBALS['wpdb']->items[7][55]);
        $saved = B2B_Matrix_DB::save_draft(7,'Να μείνει',B2B_Matrix_DB::get_draft(7)['revision']);
        $start = B2B_Preorder_List::execute_start_order(7,'',null,null,$saved['revision']);
        B2B_Preorder_Submission::run('cancel-order',7,$start['order_id'],$start['token']);
        check(B2B_Matrix_DB::get_draft(7) === $saved);
    },
];
