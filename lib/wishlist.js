const { activeCartId, addItem, itemSnapshot } = require('./cart');

async function updateWishlist(pool, userId, sessionToken, action, variantId) {
    const item = await itemSnapshot(pool, variantId);
    if (!item) throw new Error('This product variant is unavailable.');
    const [rows] = await pool.execute('SELECT id FROM wishlists WHERE user_id = ? ORDER BY id LIMIT 1', [userId]);
    let wishlistId = Number(rows[0]?.id || 0);
    if (!wishlistId) {
        const [result] = await pool.execute("INSERT INTO wishlists (user_id, name) VALUES (?, 'My wishlist')", [userId]);
        wishlistId = Number(result.insertId);
    }

    if (action === 'remove') {
        await pool.execute('DELETE FROM wishlist_items WHERE wishlist_id = ? AND variant_id = ?', [wishlistId, variantId]);
        return { message: 'Removed from your wishlist.' };
    }
    if (action === 'move_to_cart') {
        const cartId = await activeCartId(pool, userId, sessionToken);
        await addItem(pool, cartId, variantId, 1);
        await pool.execute('DELETE FROM wishlist_items WHERE wishlist_id = ? AND variant_id = ?', [wishlistId, variantId]);
        return { message: 'Moved to your bag.' };
    }
    await pool.execute('INSERT IGNORE INTO wishlist_items (wishlist_id, variant_id) VALUES (?, ?)', [wishlistId, variantId]);
    return { message: 'Saved to your wishlist.' };
}

module.exports = { updateWishlist };