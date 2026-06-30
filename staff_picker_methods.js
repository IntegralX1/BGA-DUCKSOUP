/**
 * Duck Soup: The Restaurant Game
 * Staff Picker UI — merge into ducksouptherestaurantgame.js
 *
 * ADD these methods inside the define([...], function(...) { return declare(..., { ... }); block,
 * alongside the existing methods.
 *
 * ALSO ADD to the onEnteringState() switch:
 *   case 'stHireStaff':
 *       this._showStaffPicker(gamedatas.hire_type, gamedatas.half_price || false);
 *       break;
 *
 * ALSO ADD to the onLeavingState() switch:
 *   case 'stHireStaff':
 *       this._hideStaffPicker();
 *       break;
 *
 * Dependencies already present in game JS:
 *   - this.bga.actions.performAction()
 *   - this.bga.gameArea.getElement()
 *   - g_gamethemeurl (captured as gameThemeUrl at top of setup())
 */

// =============================================================
// STAFF DATA
// Single source of truth for all 12 staff positions.
// Matches staff_box DB rows and restaurant sign back values.
// =============================================================

_staffData: {
    kitchen: [
        { type: 'chef',       label: 'Chef',       value: 90, slots: 1 },
        { type: 'sous_chef',  label: 'Sous Chef',  value: 60, slots: 1 },
        { type: 'first_cook', label: 'First Cook', value: 40, slots: 1 },
        { type: 'cook',       label: 'Cook',       value: 20, slots: 3 },
    ],
    dining_room: [
        { type: 'maitre_d',   label: "Maître d'",  value: 70, slots: 1 },
        { type: 'sommelier',  label: 'Sommelier',  value: 50, slots: 1 },
        { type: 'captain',    label: 'Captain',    value: 40, slots: 1 },
        { type: 'server',     label: 'Server',     value: 30, slots: 3 },
    ],
},

// =============================================================
// _showStaffPicker( hireType, isHalfPrice )
//
// hireType:    'kitchen' | 'dining_room' | 'either'
// isHalfPrice: boolean — true for chef_cook_bonus / maitre_d_bonus cards
//
// Reads gamedatas.staffBox (available staff in box, from server)
// and gamedatas.myStaff (this player's current excellent staff)
// to determine state of each tile.
// =============================================================

// Bug #17 — staffBox/myStaff are keyed by per-slot type (cook_1, cook_2, cook_3 for
// multi-slot roles; bare type for single-slot). The picker iterates BASE types, so we
// must aggregate across numbered slots. Rows arrive as objects from getCollectionFromDB
// (e.g. { staff_type, available } / { staff_type, is_excellent }), with values as STRINGS
// ("0"/"1"). Read the field via parseInt, not the row. Missing key → 0.
_sumStaffSlots: function(collection, baseType, slots, field) {
    let total = 0;
    if (slots > 1) {
        for (let i = 1; i <= slots; i++) {
            const row = collection[baseType + '_' + i];
            if (row !== undefined && row !== null) {
                total += parseInt(row[field], 10) || 0;
            }
        }
    } else {
        const row = collection[baseType];
        if (row !== undefined && row !== null) {
            total += parseInt(row[field], 10) || 0;
        }
    }
    return total;
},

// pickerArgs: the live state args from argHireStaff (args.args at the call site).
// Bug #22 — prefer FRESH server data (staffBox / myStaff / duckats) from these args;
// fall back to the page-load gamedatas snapshot only if args are absent (defensive).
_showStaffPicker: function(hireType, isHalfPrice, pickerArgs) {
    this._hideStaffPicker(); // Defensive — remove any stale picker

    const gamedatas    = this.gamedatas;
    const args         = (pickerArgs && typeof pickerArgs === 'object' && !Array.isArray(pickerArgs))
                         ? pickerArgs : {};

    // Fresh balance from args; gamedatas only as last-resort fallback (bug #22).
    const myDuckats    = (args.duckats != null)
                         ? parseInt(args.duckats, 10)
                         : parseInt(gamedatas.players[this.player_id].player_duckats, 10);
    // Fresh box/owned collections from args; gamedatas fallback (bug #22).
    const staffBox     = args.staffBox || gamedatas.staffBox || {};  // { slotKey: { staff_type, available } }
    const myStaff      = args.myStaff  || gamedatas.myStaff  || {};  // { slotKey: { staff_type, is_excellent } }

    // Determine which pools to show
    const pools = [];
    if (hireType === 'kitchen' || hireType === 'either') {
        pools.push({ location: 'kitchen',     label: 'Kitchen Staff',     items: this._staffData.kitchen });
    }
    if (hireType === 'dining_room' || hireType === 'either') {
        pools.push({ location: 'dining_room', label: 'Dining Room Staff', items: this._staffData.dining_room });
    }

    // Half-price filter — only show cook (chef_cook_bonus) or server (maitre_d_bonus)
    // The hireType already scopes the pool; isHalfPrice additionally restricts to one type.
    // Caller sets hireType='kitchen' for chef_cook_bonus, 'dining_room' for maitre_d_bonus.
    // The specific restricted type is indicated by a non-null half_price_type (args, bug #22)
    // or the gamedatas fallback.
    const halfPriceStaffType = isHalfPrice
        ? (args.half_price_type || gamedatas.halfPriceStaffType || null)
        : null;

    // Build modal HTML
    let sectionsHtml = '';
    pools.forEach(pool => {
        let tilesHtml = '';
        pool.items.forEach(staff => {

            // Skip if this is a half-price hire and doesn't match the restricted type
            if (halfPriceStaffType && staff.type !== halfPriceStaffType) {
                return;
            }

            // Sum availability across numbered slots (cook_1..cook_3) — available is per-slot
            // "0"/"1"; total = how many copies of this role remain in the box (bug #17).
            const availableInBox = this._sumStaffSlots(staffBox, staff.type, staff.slots, 'available');
            // Sum owned excellent copies across this player's numbered slots.
            const ownedCount     = this._sumStaffSlots(myStaff, staff.type, staff.slots, 'is_excellent');
            const remainingSlots = staff.slots - ownedCount;
            const canHire        = availableInBox > 0 && remainingSlots > 0;

            const displayValue   = isHalfPrice ? Math.floor(staff.value / 2) : staff.value;
            const canAfford      = myDuckats >= displayValue;

            // Determine tile state
            let stateClass   = '';
            let overlayHtml  = '';

            if (!canHire) {
                // Already hired all slots or not in box
                stateClass  = 'ds-staff-tile--unavailable';
                overlayHtml = '<div class="ds-staff-overlay ds-staff-overlay--hired">Already Hired</div>';
            } else if (!canAfford) {
                // Available but too expensive
                stateClass  = 'ds-staff-tile--unaffordable';
                overlayHtml = '<div class="ds-staff-overlay ds-staff-overlay--broke">Can\'t Afford</div>';
            } else {
                // Selectable
                stateClass = 'ds-staff-tile--available';
            }

            // Slot pips (for cook ×3 / server ×3) — filled = owned, empty = open
            let pipsHtml = '';
            if (staff.slots > 1) {
                for (let i = 0; i < staff.slots; i++) {
                    const filled = i < ownedCount ? 'ds-pip--filled' : 'ds-pip--empty';
                    pipsHtml += `<span class="ds-pip ${filled}"></span>`;
                }
                pipsHtml = `<div class="ds-staff-pips">${pipsHtml}</div>`;
            }

            // Half-price badge
            const halfPriceBadge = isHalfPrice
                ? '<div class="ds-half-price-badge">½ Price</div>'
                : '';

            const clickable = canHire && canAfford;
            const tileTabIndex = clickable ? 'tabindex="0"' : '';
            const tileRole     = clickable ? 'role="button"' : '';

            tilesHtml += `
                <div class="ds-staff-tile ${stateClass}"
                     data-staff-type="${staff.type}"
                     data-staff-value="${displayValue}"
                     data-clickable="${clickable ? '1' : '0'}"
                     ${tileTabIndex} ${tileRole}
                     aria-label="${staff.label}, ${displayValue} Duckats${!canHire ? ', already hired' : !canAfford ? ', cannot afford' : ''}">
                    ${halfPriceBadge}
                    ${overlayHtml}
                    <div class="ds-staff-icon ds-staff-icon--${staff.type}"></div>
                    <div class="ds-staff-label">${staff.label}</div>
                    <div class="ds-staff-value">
                        ${isHalfPrice ? `<span class="ds-staff-value--original">${staff.value}</span>` : ''}
                        <span class="ds-staff-value--display">${displayValue}</span>
                        <span class="ds-staff-value--unit">Duckats</span>
                    </div>
                    ${pipsHtml}
                </div>`;
        });

        sectionsHtml += `
            <div class="ds-staff-section">
                <h3 class="ds-staff-section-label">${pool.label}</h3>
                <div class="ds-staff-grid">${tilesHtml}</div>
            </div>`;
    });

    const titleText   = isHalfPrice ? 'Hire Staff — Half Price!' : 'Hire Staff';
    const subtitleText = `You have <strong>${myDuckats}</strong> Duckats`;

    const modalHtml = `
        <div id="ds-staff-picker-overlay" class="ds-modal-overlay" role="dialog"
             aria-modal="true" aria-label="Hire Staff">
            <div class="ds-modal ds-staff-picker">
                <div class="ds-modal-header">
                    <h2 class="ds-modal-title">${titleText}</h2>
                    <p class="ds-modal-subtitle">${subtitleText}</p>
                </div>
                <div class="ds-modal-body">
                    ${sectionsHtml}
                </div>
                <div class="ds-modal-footer">
                    <button id="ds-staff-picker-cancel" class="ds-btn ds-btn--secondary">
                        Pass — End Turn
                    </button>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Wire tile click events
    document.querySelectorAll('.ds-staff-tile[data-clickable="1"]').forEach(tile => {
        const handler = (e) => {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            this._onStaffTileSelected(tile);
        };
        tile.addEventListener('click',   handler);
        tile.addEventListener('keydown', handler);
    });

    // Wire pass/cancel button
    document.getElementById('ds-staff-picker-cancel')
        .addEventListener('click', () => this._onStaffPickerPass());

    // Trap focus inside modal
    this._trapFocus(document.getElementById('ds-staff-picker-overlay'));
},

// =============================================================
// _hideStaffPicker()
// Remove the picker modal from the DOM.
// =============================================================

_hideStaffPicker: function() {
    const overlay = document.getElementById('ds-staff-picker-overlay');
    if (overlay) {
        overlay.remove();
    }
},

// =============================================================
// _onStaffTileSelected( tile )
// Called when a player clicks an available, affordable staff tile.
// Confirms selection then fires the hireStaff AJAX action.
// =============================================================

_onStaffTileSelected: function(tile) {
    const staffType  = tile.dataset.staffType;
    const staffValue = parseInt(tile.dataset.staffValue, 10);

    // Highlight selected tile briefly for feedback
    document.querySelectorAll('.ds-staff-tile').forEach(t => {
        t.classList.remove('ds-staff-tile--selected');
    });
    tile.classList.add('ds-staff-tile--selected');

    // Disable all tiles and buttons to prevent double-fire
    document.querySelectorAll('.ds-staff-tile[data-clickable="1"]').forEach(t => {
        t.setAttribute('data-clickable', '0');
    });
    document.getElementById('ds-staff-picker-cancel').disabled = true;

    // Fire AJAX action
    this.bga.actions.performAction('hireStaff', {
        staff_type:  staffType,
        staff_value: staffValue,
    }).then(() => {
        this._hideStaffPicker();
    }).catch((err) => {
        // Re-enable on failure so player can retry
        console.error('[DuckSoup] hireStaff action failed:', err);
        tile.classList.remove('ds-staff-tile--selected');
        document.querySelectorAll('.ds-staff-tile').forEach(t => {
            t.setAttribute('data-clickable', t.dataset.originalClickable || '0');
        });
        document.getElementById('ds-staff-picker-cancel').disabled = false;
    });
},

// =============================================================
// _onStaffPickerPass()
// Player chooses not to hire — fires passHire action.
// =============================================================

_onStaffPickerPass: function() {
    document.getElementById('ds-staff-picker-cancel').disabled = true;

    this.bga.actions.performAction('passHire', {}).then(() => {
        this._hideStaffPicker();
    }).catch((err) => {
        console.error('[DuckSoup] passHire action failed:', err);
        document.getElementById('ds-staff-picker-cancel').disabled = false;
    });
},

// =============================================================
// _trapFocus( element )
// Keeps keyboard focus inside the modal while it is open.
// =============================================================

_trapFocus: function(element) {
    const focusable = element.querySelectorAll(
        'button, [tabindex="0"]'
    );
    if (!focusable.length) return;

    const first = focusable[0];
    const last  = focusable[focusable.length - 1];

    element.addEventListener('keydown', function(e) {
        if (e.key !== 'Tab') return;
        if (e.shiftKey) {
            if (document.activeElement === first) {
                e.preventDefault();
                last.focus();
            }
        } else {
            if (document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    first.focus();
},

// =============================================================
// CSS TO ADD TO ducksouptherestaurantgame.css
// (Provided here as a comment block for PD reference —
//  SE will deliver the CSS update as a separate file.)
// =============================================================

/*
--- ADD TO ducksouptherestaurantgame.css ---

.ds-staff-picker {
    width: min(720px, 96vw);
    max-height: 85vh;
    overflow-y: auto;
}

.ds-staff-section {
    margin-bottom: 24px;
}

.ds-staff-section-label {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #888;
    margin: 0 0 12px 0;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(0,0,0,0.08);
}

.ds-staff-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
}

.ds-staff-tile {
    position: relative;
    border-radius: 10px;
    border: 2px solid transparent;
    padding: 14px 10px 10px;
    text-align: center;
    background: #f5f3ee;
    transition: border-color 0.15s, transform 0.1s, box-shadow 0.15s;
    cursor: default;
    user-select: none;
    overflow: hidden;
}

.ds-staff-tile--available {
    cursor: pointer;
    border-color: #c8c0a8;
}

.ds-staff-tile--available:hover,
.ds-staff-tile--available:focus {
    border-color: #b8860b;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    outline: none;
}

.ds-staff-tile--selected {
    border-color: #2a7a2a;
    background: #f0faf0;
}

.ds-staff-tile--unavailable,
.ds-staff-tile--unaffordable {
    opacity: 0.45;
    filter: grayscale(60%);
}

.ds-staff-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-radius: 8px;
    pointer-events: none;
    z-index: 2;
}

.ds-staff-overlay--hired {
    background: rgba(60, 60, 60, 0.55);
    color: #fff;
}

.ds-staff-overlay--broke {
    background: rgba(180, 40, 40, 0.55);
    color: #fff;
}

.ds-staff-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 8px;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
}

.ds-staff-icon--chef       { background-image: url('../img/staff_chef.png'); }
.ds-staff-icon--sous_chef  { background-image: url('../img/staff_sous_chef.png'); }
.ds-staff-icon--first_cook { background-image: url('../img/staff_first_cook.png'); }
.ds-staff-icon--cook       { background-image: url('../img/staff_cook.png'); }
.ds-staff-icon--maitre_d   { background-image: url('../img/staff_maitre_d.png'); }
.ds-staff-icon--sommelier  { background-image: url('../img/staff_sommelier.png'); }
.ds-staff-icon--captain    { background-image: url('../img/staff_captain.png'); }
.ds-staff-icon--server     { background-image: url('../img/staff_server.png'); }

.ds-staff-label {
    font-size: 13px;
    font-weight: 600;
    color: #3a3028;
    margin-bottom: 4px;
}

.ds-staff-value {
    font-size: 12px;
    color: #666;
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 3px;
}

.ds-staff-value--original {
    text-decoration: line-through;
    color: #aaa;
    font-size: 11px;
}

.ds-staff-value--display {
    font-weight: 700;
    color: #b8860b;
    font-size: 15px;
}

.ds-staff-value--unit {
    font-size: 11px;
    color: #999;
}

.ds-staff-pips {
    display: flex;
    justify-content: center;
    gap: 4px;
    margin-top: 6px;
}

.ds-pip {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: 1.5px solid #999;
    display: inline-block;
}

.ds-pip--filled {
    background: #2a7a2a;
    border-color: #2a7a2a;
}

.ds-pip--empty {
    background: transparent;
}

.ds-half-price-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    background: #c0392b;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 5px;
    border-radius: 4px;
    z-index: 3;
}

--- END CSS ADDITION ---
*/
