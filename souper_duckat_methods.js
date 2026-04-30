/**
 * Duck Soup: The Restaurant Game
 * Souper Duckat UI — merge into ducksouptherestaurantgame.js
 *
 * MERGE INSTRUCTIONS:
 * 1. Add all methods below inside the declare({...}) block alongside existing methods.
 *
 * 2. In setup(), after building the player header HTML, call:
 *      this._buildSouperDuckatPanel(player_id, playerData.player_souper_duckats);
 *
 * 3. In onEnteringState() add these cases:
 *
 *      case 'stPlayerQuestion':
 *          // Buy/cash available from turn start
 *          if (this.isCurrentPlayerActive()) {
 *              this._setSouperDuckatBuyCashEnabled(true);
 *              this._setSouperDuckatUseEnabled(false);
 *          }
 *          break;
 *
 *      case 'stPlayerMove':
 *          // Buy/cash closes when move dice are about to be rolled
 *          if (this.isCurrentPlayerActive()) {
 *              this._setSouperDuckatBuyCashEnabled(false);
 *              this._setSouperDuckatUseEnabled(false);
 *          }
 *          break;
 *
 *      case 'stSouperDuckatUse':
 *          // Post-movement: player may spend Souper Duckats for extra squares
 *          if (this.isCurrentPlayerActive()) {
 *              this._setSouperDuckatBuyCashEnabled(false);
 *              this._setSouperDuckatUseEnabled(true);
 *          }
 *          break;
 *
 * 4. In onLeavingState() add:
 *      case 'stPlayerQuestion':
 *      case 'stPlayerMove':
 *      case 'stSouperDuckatUse':
 *          this._setSouperDuckatBuyCashEnabled(false);
 *          this._setSouperDuckatUseEnabled(false);
 *          break;
 *
 * 5. In the notifications handler, add:
 *      'souperDuckatUpdate': function(notif) {
 *          this._updateSouperDuckatCount(notif.args.player_id, notif.args.souper_duckats);
 *          this._updateDuckatCount(notif.args.player_id, notif.args.duckats);
 *      },
 *
 * 6. In getAllDatas() response on the server, ensure player data includes
 *    player_souper_duckats so the panel renders the correct starting count.
 *
 * GAME STATE DEPENDENCY:
 *   stSouperDuckatUse must exist in states.inc.php (add if not present):
 *     State ID suggested: 11 (after existing states 2-10)
 *     Triggered after stPlayerMove resolves normal movement.
 *     If player has 0 Souper Duckats, auto-transition past this state.
 */

// =============================================================
// _buildSouperDuckatPanel( playerId, initialCount )
//
// Injects the Souper Duckat sub-panel into the player's header.
// Called once per player during setup().
// Only the active player sees interactive controls — others see
// a read-only count display.
// =============================================================

_buildSouperDuckatPanel: function(playerId, initialCount) {
    const isMe = (parseInt(playerId, 10) === parseInt(this.player_id, 10));

    // Target: the player header panel already built in setup()
    // Assumes a container with id="ds-player-header-{playerId}" exists.
    const headerEl = document.getElementById(`ds-player-header-${playerId}`);
    if (!headerEl) {
        console.warn(`[DuckSoup] Player header not found for player ${playerId}`);
        return;
    }

    const count = parseInt(initialCount, 10) || 0;

    if (isMe) {
        // Full interactive panel for the local player
        const panelHtml = `
            <div id="ds-sd-panel-${playerId}" class="ds-sd-panel">

                <!-- Souper Duckat count display -->
                <div class="ds-sd-count-row">
                    <span class="ds-sd-icon" title="Souper Duckats">🦆</span>
                    <span class="ds-sd-label">Souper Duckats</span>
                    <span id="ds-sd-count-${playerId}" class="ds-sd-count">${count}</span>
                </div>

                <!-- Buy / Cash controls (visible before move roll) -->
                <div id="ds-sd-buycash-${playerId}" class="ds-sd-buycash ds-sd-controls--hidden">
                    <button id="ds-sd-buy-${playerId}"
                            class="ds-sd-btn ds-sd-btn--buy"
                            title="Buy 1 Souper Duckat for 50 Duckats"
                            aria-label="Buy Souper Duckat (50 Duckats)">
                        + Buy <span class="ds-sd-cost">(50🪙)</span>
                    </button>
                    <button id="ds-sd-cash-${playerId}"
                            class="ds-sd-btn ds-sd-btn--cash"
                            title="Cash in 1 Souper Duckat for 25 Duckats"
                            aria-label="Cash Souper Duckat (25 Duckats)">
                        − Cash <span class="ds-sd-cost">(25🪙)</span>
                    </button>
                </div>

                <!-- Use controls (visible after normal movement) -->
                <div id="ds-sd-use-${playerId}" class="ds-sd-use ds-sd-controls--hidden">
                    <div class="ds-sd-use-row">
                        <button id="ds-sd-use-minus-${playerId}"
                                class="ds-sd-counter-btn"
                                aria-label="Decrease Souper Duckats to spend">−</button>
                        <span id="ds-sd-use-qty-${playerId}" class="ds-sd-use-qty">0</span>
                        <button id="ds-sd-use-plus-${playerId}"
                                class="ds-sd-counter-btn"
                                aria-label="Increase Souper Duckats to spend">+</button>
                        <span class="ds-sd-use-label">extra sq.</span>
                    </div>
                    <div class="ds-sd-use-actions">
                        <button id="ds-sd-use-confirm-${playerId}"
                                class="ds-sd-btn ds-sd-btn--use"
                                aria-label="Spend selected Souper Duckats">
                            Spend &amp; Move
                        </button>
                        <button id="ds-sd-use-skip-${playerId}"
                                class="ds-sd-btn ds-sd-btn--skip"
                                aria-label="Skip — do not spend Souper Duckats">
                            Skip
                        </button>
                    </div>
                </div>

            </div>`;

        headerEl.insertAdjacentHTML('beforeend', panelHtml);
        this._wireSouperDuckatEvents(playerId);

    } else {
        // Read-only display for other players
        const readOnlyHtml = `
            <div id="ds-sd-panel-${playerId}" class="ds-sd-panel ds-sd-panel--readonly">
                <span class="ds-sd-icon" title="Souper Duckats">🦆</span>
                <span class="ds-sd-label">Souper Duckats</span>
                <span id="ds-sd-count-${playerId}" class="ds-sd-count">${count}</span>
            </div>`;
        headerEl.insertAdjacentHTML('beforeend', readOnlyHtml);
    }
},

// =============================================================
// _wireSouperDuckatEvents( playerId )
// Attaches all button event listeners for the local player panel.
// =============================================================

_wireSouperDuckatEvents: function(playerId) {
    const pid = playerId;

    // --- Buy button ---
    document.getElementById(`ds-sd-buy-${pid}`)
        .addEventListener('click', () => this._onSouperDuckatBuy(pid));

    // --- Cash button ---
    document.getElementById(`ds-sd-cash-${pid}`)
        .addEventListener('click', () => this._onSouperDuckatCash(pid));

    // --- Use: minus button ---
    document.getElementById(`ds-sd-use-minus-${pid}`)
        .addEventListener('click', () => this._adjustUseQty(pid, -1));

    // --- Use: plus button ---
    document.getElementById(`ds-sd-use-plus-${pid}`)
        .addEventListener('click', () => this._adjustUseQty(pid, 1));

    // --- Use: confirm spend ---
    document.getElementById(`ds-sd-use-confirm-${pid}`)
        .addEventListener('click', () => this._onSouperDuckatUseConfirm(pid));

    // --- Use: skip ---
    document.getElementById(`ds-sd-use-skip-${pid}`)
        .addEventListener('click', () => this._onSouperDuckatSkip(pid));
},

// =============================================================
// ENABLE / DISABLE CONTROL GROUPS
// Called from onEnteringState / onLeavingState.
// =============================================================

_setSouperDuckatBuyCashEnabled: function(enabled) {
    const pid = this.player_id;
    const el  = document.getElementById(`ds-sd-buycash-${pid}`);
    if (!el) return;

    if (enabled) {
        el.classList.remove('ds-sd-controls--hidden');
        this._refreshBuyCashState(pid);
    } else {
        el.classList.add('ds-sd-controls--hidden');
    }
},

_setSouperDuckatUseEnabled: function(enabled) {
    const pid = this.player_id;
    const el  = document.getElementById(`ds-sd-use-${pid}`);
    if (!el) return;

    if (enabled) {
        // Reset quantity counter to 0 each time use panel opens
        this._setUseQty(pid, 0);
        el.classList.remove('ds-sd-controls--hidden');
        this._refreshUseState(pid);
    } else {
        el.classList.add('ds-sd-controls--hidden');
    }
},

// =============================================================
// BUY / CASH ACTIONS
// =============================================================

_onSouperDuckatBuy: function(playerId) {
    const buyBtn  = document.getElementById(`ds-sd-buy-${playerId}`);
    const cashBtn = document.getElementById(`ds-sd-cash-${playerId}`);
    if (!buyBtn || buyBtn.disabled) return;

    buyBtn.disabled  = true;
    cashBtn.disabled = true;

    this.bga.actions.performAction('buySouperDuckat', {}).then(() => {
        // Server will fire souperDuckatUpdate notification to update counts
        this._refreshBuyCashState(playerId);
        buyBtn.disabled  = false;
        cashBtn.disabled = false;
    }).catch((err) => {
        console.error('[DuckSoup] buySouperDuckat failed:', err);
        buyBtn.disabled  = false;
        cashBtn.disabled = false;
    });
},

_onSouperDuckatCash: function(playerId) {
    const buyBtn  = document.getElementById(`ds-sd-buy-${playerId}`);
    const cashBtn = document.getElementById(`ds-sd-cash-${playerId}`);
    if (!cashBtn || cashBtn.disabled) return;

    buyBtn.disabled  = true;
    cashBtn.disabled = true;

    this.bga.actions.performAction('cashSouperDuckat', {}).then(() => {
        this._refreshBuyCashState(playerId);
        buyBtn.disabled  = false;
        cashBtn.disabled = false;
    }).catch((err) => {
        console.error('[DuckSoup] cashSouperDuckat failed:', err);
        buyBtn.disabled  = false;
        cashBtn.disabled = false;
    });
},

// =============================================================
// USE COUNTER LOGIC
// =============================================================

_adjustUseQty: function(playerId, delta) {
    const qtyEl = document.getElementById(`ds-sd-use-qty-${playerId}`);
    if (!qtyEl) return;

    const currentQty    = parseInt(qtyEl.textContent, 10) || 0;
    const souperCount   = this._getSouperDuckatCount(playerId);
    const newQty        = Math.max(0, Math.min(souperCount, currentQty + delta));

    this._setUseQty(playerId, newQty);
},

_setUseQty: function(playerId, qty) {
    const qtyEl      = document.getElementById(`ds-sd-use-qty-${playerId}`);
    const minusBtn   = document.getElementById(`ds-sd-use-minus-${playerId}`);
    const plusBtn    = document.getElementById(`ds-sd-use-plus-${playerId}`);
    const confirmBtn = document.getElementById(`ds-sd-use-confirm-${playerId}`);
    if (!qtyEl) return;

    const souperCount = this._getSouperDuckatCount(playerId);
    qtyEl.textContent = qty;

    // Clamp buttons
    if (minusBtn)   minusBtn.disabled   = (qty <= 0);
    if (plusBtn)    plusBtn.disabled    = (qty >= souperCount);

    // Confirm only active when qty > 0
    if (confirmBtn) confirmBtn.disabled = (qty === 0);
},

_onSouperDuckatUseConfirm: function(playerId) {
    const qtyEl = document.getElementById(`ds-sd-use-qty-${playerId}`);
    if (!qtyEl) return;

    const qty = parseInt(qtyEl.textContent, 10) || 0;
    if (qty <= 0) return;

    // Disable all use controls during action
    this._setUseControlsDisabled(playerId, true);

    this.bga.actions.performAction('useSouperDuckats', { quantity: qty }).then(() => {
        this._setSouperDuckatUseEnabled(false);
    }).catch((err) => {
        console.error('[DuckSoup] useSouperDuckats failed:', err);
        this._setUseControlsDisabled(playerId, false);
    });
},

_onSouperDuckatSkip: function(playerId) {
    this._setUseControlsDisabled(playerId, true);

    this.bga.actions.performAction('skipSouperDuckats', {}).then(() => {
        this._setSouperDuckatUseEnabled(false);
    }).catch((err) => {
        console.error('[DuckSoup] skipSouperDuckats failed:', err);
        this._setUseControlsDisabled(playerId, false);
    });
},

_setUseControlsDisabled: function(playerId, disabled) {
    ['ds-sd-use-minus', 'ds-sd-use-plus',
     'ds-sd-use-confirm', 'ds-sd-use-skip'].forEach(prefix => {
        const el = document.getElementById(`${prefix}-${playerId}`);
        if (el) el.disabled = disabled;
    });
},

// =============================================================
// STATE REFRESH HELPERS
// =============================================================

/**
 * Enable/disable Buy and Cash buttons based on current Duckat
 * and Souper Duckat balances.
 * Buy  requires >= 50 Duckats.
 * Cash requires >= 1 Souper Duckat.
 */
_refreshBuyCashState: function(playerId) {
    const duckats       = this._getDuckatCount(playerId);
    const souperCount   = this._getSouperDuckatCount(playerId);

    const buyBtn  = document.getElementById(`ds-sd-buy-${playerId}`);
    const cashBtn = document.getElementById(`ds-sd-cash-${playerId}`);

    if (buyBtn)  buyBtn.disabled  = (duckats < 50);
    if (cashBtn) cashBtn.disabled = (souperCount < 1);
},

/**
 * Sync the use counter max with current Souper Duckat count.
 * Called when use panel opens or count changes mid-state.
 */
_refreshUseState: function(playerId) {
    const souperCount = this._getSouperDuckatCount(playerId);
    const qtyEl       = document.getElementById(`ds-sd-use-qty-${playerId}`);
    if (!qtyEl) return;

    // If player has no Souper Duckats, auto-skip
    if (souperCount === 0) {
        this._onSouperDuckatSkip(playerId);
        return;
    }

    // Clamp any existing qty to new max (e.g. if count dropped mid-turn)
    const currentQty = parseInt(qtyEl.textContent, 10) || 0;
    this._setUseQty(playerId, Math.min(currentQty, souperCount));
},

// =============================================================
// COUNT ACCESSORS
// Read live values from the DOM (source of truth after notifications).
// =============================================================

_getSouperDuckatCount: function(playerId) {
    const el = document.getElementById(`ds-sd-count-${playerId}`);
    return el ? parseInt(el.textContent, 10) || 0 : 0;
},

_getDuckatCount: function(playerId) {
    // Reads from the existing Duckat counter element built in setup().
    // Adjust selector to match the actual element id used in your header.
    const el = document.getElementById(`ds-player-duckats-${playerId}`);
    return el ? parseInt(el.textContent, 10) || 0 : 0;
},

// =============================================================
// NOTIFICATION HANDLER
// Updates both the Souper Duckat count pip and Duckat count
// when the server fires a souperDuckatUpdate notification.
// =============================================================

_updateSouperDuckatCount: function(playerId, newCount) {
    const el = document.getElementById(`ds-sd-count-${playerId}`);
    if (el) el.textContent = parseInt(newCount, 10) || 0;

    // Re-evaluate button states if controls are currently visible
    const buyCashEl = document.getElementById(`ds-sd-buycash-${playerId}`);
    if (buyCashEl && !buyCashEl.classList.contains('ds-sd-controls--hidden')) {
        this._refreshBuyCashState(playerId);
    }
    const useEl = document.getElementById(`ds-sd-use-${playerId}`);
    if (useEl && !useEl.classList.contains('ds-sd-controls--hidden')) {
        this._refreshUseState(playerId);
    }
},

_updateDuckatCount: function(playerId, newCount) {
    // Updates the existing Duckat counter element in the player header.
    const el = document.getElementById(`ds-player-duckats-${playerId}`);
    if (el) el.textContent = parseInt(newCount, 10) || 0;
},
