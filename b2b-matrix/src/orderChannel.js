/** Selection survives list refreshes, but never an account/permission change. */
export function orderChannelSelection(payload, previous = null) {
 const userId = Number(payload.user_id) || 0;
 const canChoose = payload.orderChannelContext?.canChoose === true;
 let live = canChoose && (previous?.userId === userId && previous.canChoose === canChoose
  ? previous.live === true : payload.orderChannelContext?.defaultLive === true);
 if (payload.activeBatch) live = canChoose && payload.activeBatch.order_channel === 'live';
 return { userId, canChoose, live };
}
