/**
 * ducksouptherestaurantgame.js
 *
 * Duck Soup — The Restaurant Game
 * BGA implementation: Phase 2
 *
 * Handles:
 *   - setup()                 Initial UI build from gamedatas
 *   - onEnteringState()       Show/hide controls per game state
 *   - onLeavingState()        Clean up UI on state exit
 *   - onUpdateActionButtons() Add BGA action bar buttons per state
 *   - Player action handlers  Wire button clicks → bga.actions.performAction → action.php
 *   - Notification handlers   React to server events → update UI
 *   - Staff board navigation  Arrow buttons cycle through player boards
 *   - Pawn positioning        Place/animate pawns on board squares
 *   - Duckat counters         Live counter updates via BGA ebg/counter
 */

define([
    'dojo',
    'dojo/_base/declare',
    'ebg/core/gamegui',
    'ebg/counter',
    'ebg/stock'
],
function (dojo, declare) {
    return declare('bgagame.ducksouptherestaurantgame', ebg.core.gamegui, {

        constructor: function () {
            console.log('ducksouptherestaurantgame constructor');

            // Staff board navigation
            this.currentBoardIndex = 0;
            this.playerOrder        = [];   // ordered array of player_ids

            // BGA counters — one per player for Duckats + Souper Duckats
            this.duckatCounters       = {};
            this.souperDuckatCounters = {};

            // Pawn position tracking
            this.pawnPositions = {};

            // Board square coordinates (% from top-left of board image)
            // 36 squares, clockwise from bottom-left (Duck Soup square = 0)
            // These percentages map each square index to a CSS top/left position
            this.squareCoords = [
                // Bottom row (left → right): squares 0–8
                {l: 3,  t: 88}, {l: 12, t: 88}, {l: 21, t: 88},
                {l: 30, t: 88}, {l: 39, t: 88}, {l: 48, t: 88},
                {l: 57, t: 88}, {l: 66, t: 88}, {l: 75, t: 88},
                // Right column (bottom → top): squares 9–17
                {l: 88, t: 88}, {l: 88, t: 79}, {l: 88, t: 70},
                {l: 88, t: 61}, {l: 88, t: 52}, {l: 88, t: 43},
                {l: 88, t: 34}, {l: 88, t: 25}, {l: 88, t: 16},
                // Top row (right → left): squares 18–26
                {l: 88, t: 3},  {l: 79, t: 3},  {l: 70, t: 3},
                {l: 61, t: 3},  {l: 52, t: 3},  {l: 43, t: 3},
                {l: 34, t: 3},  {l: 25, t: 3},  {l: 16, t: 3},
                // Left column (top → bottom): squares 27–31
                {l: 3,  t: 3},  {l: 3,  t: 12}, {l: 3,  t: 21},
                {l: 3,  t: 30}, {l: 3,  t: 39}
            ];

            // Staff type → grid slot index (0-based, matches TPL grid-item order)
            this.staffSlotIndex = {
                'chef':       0,  'sous_chef':  1, 'first_cook': 2,
                'cook_1':     3,  'cook_2':     4, 'cook_3':     5,
                'maitre_d':   6,  'sommelier':  7, 'captain':    8,
                'server_1':   9,  'server_2':  10, 'server_3':  11
            };

            // Staff type → display name for notifications
            this.staffNames = {
                'chef':       _('Executive Chef'),
                'sous_chef':  _('Sous Chef'),
                'first_cook': _('First Cook'),
                'cook_1':     _('Cook'),
                'cook_2':     _('Cook'),
                'cook_3':     _('Cook'),
                'maitre_d':   _("Maître d'"),
                'sommelier':  _('Sommelier'),
                'captain':    _('Captain'),
                'server_1':   _('Server'),
                'server_2':   _('Server'),
                'server_3':   _('Server')
            };

            // Player colour → pawn image filename suffix
            this.pawnColors = {
                'ff0000': 'red',
                '008000': 'green',
                '0000ff': 'blue',
                '800080': 'purple'
            };
        },

        // ==============================================================
        // SETUP
        // ==============================================================

        setup: function (gamedatas) {
            console.log('Starting game setup', gamedatas);

            this.gamedatas = gamedatas;
            var gameThemeUrl = g_gamethemeurl; // BGA global — available by setup() time
            this.gameThemeUrl = gameThemeUrl;

                // Root-level art loads on demand via ${gameThemeUrl} in the injected HTML,
                // so skip BGA's up-front preload to speed up table start.
                this.bga.images.dontPreloadImages([
                    'board.jpg',
                    'inner-board.png',
                    'staff-board.jpg',
                    'staff-die.png',
                    'movement-dice.png',
                    'super-duckats.png',
                    'duckats.png',
                    'dice-1.png', 'dice-2.png', 'dice-3.png',
                    'dice-4.png', 'dice-5.png', 'dice-6.png',
                    'pawn-blue.png', 'pawn-green.png', 'pawn-purple.png', 'pawn-red.png'
                ]);

            // ---------------------------------------------------------------
            // Inject full game HTML into BGA game area (replaces TPL/view)
            // ---------------------------------------------------------------
            this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
                <div class="container">
                    <div class="clearfix">
                        <div class="right-content">
                            <div class="letter-buttons">
                                <button id="letter-a" disabled>A</button>
                                <button id="letter-b" disabled>B</button>
                                <button id="letter-c" disabled>C</button>
                                <button id="letter-d" disabled>D</button>
                            </div>
                            <div id="question-modal" class="modal" style="display:none;">
                                <div class="modal-content">
                                    <span class="close-button">&times;</span>
                                    <p id="question-text"></p>
                                    <div id="question-answers" class="question-answers">
                                        <button class="answer-btn" data-answer="A" id="answer-a" disabled></button>
                                        <button class="answer-btn" data-answer="B" id="answer-b" disabled></button>
                                        <button class="answer-btn" data-answer="C" id="answer-c" disabled></button>
                                        <button class="answer-btn" data-answer="D" id="answer-d" disabled></button>
                                    </div>
                                </div>
                            </div>
                            <div class="dice-buttons">
                                <button id="staff-die" style="display:none;">
                                    <div id="staff-die-image">
                                        <img src="${gameThemeUrl}img/staff-die.png" alt="Staff Die">
                                    </div>
                                    <span>Roll the<br>Staff Die</span>
                                </button>
                                <button id="move-die" style="display:none;">
                                    <div id="move-die-image">
                                        <img src="${gameThemeUrl}img/movement-dice.png" alt="Movement Dice">
                                    </div>
                                    <span>Roll for<br>Movement</span>
                                </button>
                            </div>
                            <div class="staff-board-container">
                                <button id="left-arrow" class="arrow"><span></span></button>
                                <button id="right-arrow" class="arrow"><span></span></button>
                                <div id="staff-boards-wrapper"></div>
                            </div>
                        </div>
                        <div class="left-content">
                            <div id="board-container">
                                <div id="board">
                                    <img class="board-img" src="${gameThemeUrl}img/board.jpg" alt="Duck Soup Board">
                                    <div id="inner-board">
                                        <img src="${gameThemeUrl}img/inner-board.png" alt="Duck Soup">
                                    </div>
                                    <div class="board-contents inactive" id="board-contents">
                                        <h2 id="board-msg-title"></h2>
                                        <p id="board-msg-body"></p>
                                    </div>
                                    <div id="pawns-layer"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `);



            // Build ordered player list (turn order)
            this.playerOrder = Object.keys(gamedatas.players);

            // Initialise Duckat counters and staff boards for each player
            for (var player_id in gamedatas.players) {
                var player = gamedatas.players[player_id];
                this._initPlayerBoard(player_id, player);
            }

            // Place pawns on board
            for (var player_id in gamedatas.players) {
                var player = gamedatas.players[player_id];
                this._placePawn(player_id, player.board_position, player.color);
            }

            // Show the first player's staff board
            this._showStaffBoard(0);

            // Apply all Excellent staff tiles after every board is in the DOM
            this._applyAllExcellentStaff();

            // Wire staff board arrow navigation
            dojo.connect(dojo.byId('left-arrow'),  'onclick', this, '_onLeftArrow');
            dojo.connect(dojo.byId('right-arrow'), 'onclick', this, '_onRightArrow');

            // Wire dice buttons — staff die and movement dice
            dojo.connect(dojo.byId('staff-die'), 'onclick', this, 'onRollStaffDie');
            dojo.connect(dojo.byId('move-die'),  'onclick', this, 'onRollMovement');

            // Wire letter buttons (A/B/C/D) — active only in chooseQuestion state
            dojo.query('.letter-buttons button').forEach(dojo.hitch(this, function (btn) {
                dojo.connect(btn, 'onclick', this, '_onLetterButton');
            }));

            // Wire answer buttons in the question modal
            dojo.query('.answer-btn').forEach(dojo.hitch(this, function (btn) {
                dojo.connect(btn, 'onclick', this, '_onAnswerButton');
            }));

            // Wire modal close button
            var closeBtn = dojo.query('.close-button')[0];
            if (closeBtn) {
                dojo.connect(closeBtn, 'onclick', this, '_closeQuestionModal');
            }

            // Show inner board duck logo by default
            this._showInnerBoard(true);

            // Subscribe to all server notifications
            this.setupNotifications();

            console.log('Game setup complete');
        },

        // ==============================================================
        // PLAYER BOARD INITIALISATION
        // ==============================================================

        _initPlayerBoard: function (player_id, player) {
            var gameThemeUrl = this.gameThemeUrl || g_gamethemeurl || '';
            // Build player board HTML dynamically and inject into wrapper
            var color     = player.color || 'ff0000';
            var name      = player.name  || '';
            var duckats   = player.duckats        || 0;
            var souper    = player.souper_duckats || 0;
            console.log('Player board init — id:', player_id, 'color:', color, 'name:', name, 'duckats:', duckats);

            var boardHtml = `
                <div id="staff-board-${player_id}" class="staff-board-panel" style="display:none;">
                    <div class="player-header" style="background-color:#${color};">
                        <div class="player-name">${name}</div>
                        <div class="player-stats">
                            <div class="super-duckat">
                                <div class="value" id="souper-duckat-count-${player_id}">${souper}</div>
                                <img src="${gameThemeUrl}img/super-duckats.png" alt="Souper Duckats">
                            </div>
                            <div class="duckat">
                                <div class="value" id="duckat-count-${player_id}">${duckats}</div>
                                <img src="${gameThemeUrl}img/duckats.png" alt="Duckats">
                            </div>
                        </div>
                    </div>
                    <div class="staff-board-wrap">
                        <img class="staff-board-img" src="${gameThemeUrl}img/staff-board.jpg" alt="Staff Board">
                        <div class="card-grid">
                            <div class="grid-item" id="ex-chef-${player_id}"></div>
                            <div class="grid-item" id="ex-sous-chef-${player_id}"></div>
                            <div class="grid-item" id="ex-first-cook-${player_id}"></div>
                            <div class="grid-item" id="ex-cook-1-${player_id}"></div>
                            <div class="grid-item" id="ex-cook-2-${player_id}"></div>
                            <div class="grid-item" id="ex-cook-3-${player_id}"></div>
                            <div class="grid-item" id="ex-maitre-d-${player_id}"></div>
                            <div class="grid-item" id="ex-sommelier-${player_id}"></div>
                            <div class="grid-item" id="ex-captain-${player_id}"></div>
                            <div class="grid-item" id="ex-server-1-${player_id}"></div>
                            <div class="grid-item" id="ex-server-2-${player_id}"></div>
                            <div class="grid-item" id="ex-server-3-${player_id}"></div>
                        </div>
                    </div>
                </div>`;

            var wrapper = dojo.byId('staff-boards-wrapper');
            if (wrapper) {
                wrapper.insertAdjacentHTML('beforeend', boardHtml);
            }

            // Duckat counter
            var duckatEl = dojo.byId('duckat-count-' + player_id);
            if (duckatEl) {
                this.duckatCounters[player_id] = new ebg.counter();
                this.duckatCounters[player_id].create(duckatEl);
                this.duckatCounters[player_id].setValue(duckats);
            }

            // Souper Duckat counter
            var souperEl = dojo.byId('souper-duckat-count-' + player_id);
            if (souperEl) {
                this.souperDuckatCounters[player_id] = new ebg.counter();
                this.souperDuckatCounters[player_id].create(souperEl);
                this.souperDuckatCounters[player_id].setValue(souper);
            }

            // Build Souper Duckat panel inside the player header
            this._buildSouperDuckatPanel(player_id, souper);

        },

        // ==============================================================
        // STAFF BOARD NAVIGATION
        // ==============================================================

        _showStaffBoard: function (index) {
            // Hide all staff board panels
            dojo.query('.staff-board-panel').forEach(function (el) {
                dojo.style(el, 'display', 'none');
            });

            // Show the target panel
            var targetId = this.playerOrder[index];
            var panel    = dojo.byId('staff-board-' + targetId);
            if (panel) {
                dojo.style(panel, 'display', 'block');
            }

            this.currentBoardIndex = index;
        },

        _onLeftArrow: function () {
            var newIndex = (this.currentBoardIndex - 1 + this.playerOrder.length)
                           % this.playerOrder.length;
            this._showStaffBoard(newIndex);
        },

        _onRightArrow: function () {
            var newIndex = (this.currentBoardIndex + 1) % this.playerOrder.length;
            this._showStaffBoard(newIndex);
        },

        // ==============================================================
        // STAFF TILE DISPLAY
        // ==============================================================

        // staff_type (DB) → image filename base in img/staff_cards/
        // Numbered slots (cook_1/2/3, server_1/2/3) share one tile image.
        _staffTileImage: function (staffType) {
            var base = staffType.replace(/_\d+$/, '');   // cook_1 -> cook
            var map = {
                'chef':       'ex-chef',
                'sous_chef':  'ex-sous-chef',
                'first_cook': 'ex-first-cook',
                'cook':       'ex-cook',
                'maitre_d':   'ex-maitre-d',
                'sommelier':  'ex-sommelier',
                'captain':    'ex-captain',
                'server':     'ex-server'
            };
            return map[base] || null;
        },

        // staff_type (DB) → DOM id suffix used in the board grid
        // (matches the ex-...-${player_id} ids built in setup()).
        _staffSlotIdSuffix: function (staffType) {
            return staffType.replace(/_/g, '-');   // cook_1 -> cook-1, first_cook -> first-cook
        },

        _showExcellentStaff: function (player_id, staffType) {
            var imgBase = this._staffTileImage(staffType);
            if (!imgBase) { return; }

            var slotId = 'ex-' + this._staffSlotIdSuffix(staffType) + '-' + player_id;
            var slot   = dojo.byId(slotId);
            if (!slot) { return; }

            // Avoid double-injecting if already shown
            if (slot.querySelector('.ds-excellent-tile')) { return; }

            var imgUrl = this.gameThemeUrl + 'img/staff_cards/' + imgBase + '.jpg';

            // Flexbox overlay container fills the slot and centres the tile,
            // sitting on top of the average staff printed on the board image.
            slot.style.position       = 'relative';
            slot.style.display        = 'flex';
            slot.style.alignItems     = 'center';
            slot.style.justifyContent = 'center';

            slot.insertAdjacentHTML('beforeend',
                '<div class="ds-excellent-tile" style="' +
                    'position:absolute;top:0;left:0;width:100%;height:100%;' +
                    'display:flex;align-items:center;justify-content:center;' +
                    'pointer-events:none;z-index:5;">' +
                    '<img src="' + imgUrl + '" alt="" style="' +
                        'max-width:100%;max-height:100%;width:auto;height:auto;' +
                        'object-fit:contain;display:block;">' +
                '</div>'
            );
        },

        _applyAllExcellentStaff: function () {
            if (!this.gamedatas || !this.gamedatas.staff) { return; }
            for (var staff_id in this.gamedatas.staff) {
                var tile = this.gamedatas.staff[staff_id];
                if (tile.is_excellent == 1) {
                    this._showExcellentStaff(tile.player_id, tile.staff_type);
                }
            }
        },

        _hideExcellentStaff: function (player_id, staffType) {
            var slotId = 'ex-' + this._staffSlotIdSuffix(staffType) + '-' + player_id;
            var slot   = dojo.byId(slotId);
            if (!slot) { return; }

            var tile = slot.querySelector('.ds-excellent-tile');
            if (tile) {
                tile.parentNode.removeChild(tile);
            }
        },

        // ==============================================================
        // PAWN MANAGEMENT
        // ==============================================================

        _placePawn: function (player_id, position, color) {
            // Create pawn element if it doesn't exist yet
            var pawnEl = dojo.byId('pawn-' + player_id);
            if (!pawnEl) {
                var pawnsLayer = dojo.byId('pawns-layer');
                var colorName  = this.pawnColors[color] || 'red';
                if (pawnsLayer) {
                    pawnsLayer.insertAdjacentHTML('beforeend',
                        '<div class="pawn-token pawn-' + colorName + '" id="pawn-' + player_id + '">' +
                        '<img src="' + this.gameThemeUrl + 'img/pawn-' + colorName + '.png" alt="pawn"></div>'
                    );
                    pawnEl = dojo.byId('pawn-' + player_id);
                }
            }
            var boardEl = dojo.byId('board');
            if (!pawnEl || !boardEl) { return; }

            var coords = this.squareCoords[position % 32];

            // Offset slightly per player to avoid exact overlap
            var playerIndex = this.playerOrder.indexOf(String(player_id));
            var offsetL     = (playerIndex % 2) * 3;
            var offsetT     = (Math.floor(playerIndex / 2)) * 3;

            dojo.style(pawnEl, {
                position: 'absolute',
                left:     (coords.l + offsetL) + '%',
                top:      (coords.t + offsetT) + '%',
                transition: 'left 0.6s ease, top 0.6s ease'
            });

            this.pawnPositions[player_id] = position;
        },

        _animatePawn: function (player_id, newPosition, color) {
            this._placePawn(player_id, newPosition, color);
        },

        // ==============================================================
        // BOARD CONTENT AREA
        // ==============================================================

        _showInnerBoard: function (show) {
            var innerBoard   = dojo.byId('inner-board');
            var boardContent = dojo.query('.board-contents')[0];

            if (show) {
                if (innerBoard)   { dojo.removeClass(innerBoard,   'inactive'); }
                if (boardContent) { dojo.addClass(boardContent,    'inactive'); }
            } else {
                if (innerBoard)   { dojo.addClass(innerBoard,      'inactive'); }
                if (boardContent) { dojo.removeClass(boardContent, 'inactive'); }
            }
        },

        _showBoardMessage: function (title, body) {
            this._showInnerBoard(false);
            var titleEl = dojo.byId('board-msg-title');
            var bodyEl  = dojo.byId('board-msg-body');
            if (titleEl) { titleEl.innerHTML = title; }
            if (bodyEl)  { bodyEl.innerHTML  = body;  }
        },

        // ==============================================================
        // QUESTION MODAL
        // ==============================================================

        _openQuestionModal: function (question) {
            var modal = dojo.byId('question-modal');
            if (!modal) { return; }
            // Move modal to document.body to escape BGA's pointer-event capture
            if (modal.parentNode !== document.body) {
                document.body.appendChild(modal);
            }

            // Set question text
            dojo.byId('question-text').innerHTML = question.question_text || '';

            // Set answer button labels
            var answers = { A: question.answer_a, B: question.answer_b,
                            C: question.answer_c, D: question.answer_d };

            dojo.query('.answer-btn').forEach(function (btn) {
                var letter = btn.getAttribute('data-answer');
                var text   = answers[letter];

                if (text) {
                    btn.innerHTML = '<strong>' + letter + '.</strong> ' + text;
                    btn.disabled  = false;  // ensure clickable
                    dojo.style(btn, 'display', 'inline-block');
                } else {
                    // Hide C/D for True/False and 3-option questions
                    dojo.style(btn, 'display', 'none');
                }
                dojo.removeClass(btn, 'selected correct wrong');
            });

            dojo.style(modal, 'display', 'flex');
        },

        _closeQuestionModal: function () {
            var modal = dojo.byId('question-modal');
            if (modal) { dojo.style(modal, 'display', 'none'); }
        },

        _lockAnswerButtons: function () {
            dojo.query('.answer-btn').forEach(function (btn) {
                btn.disabled = true;
            });
        },

        // ==============================================================
        // STATE: onEnteringState
        // ==============================================================

        onEnteringState: function (stateName, args) {
            console.log('Entering state:', stateName, args);

            switch (stateName) {

                case 'chooseQuestion':
                    // Enable letter buttons for active player only
                    if (this.isCurrentPlayerActive()) {
                        dojo.query('.letter-buttons button').forEach(function (btn) {
                            btn.disabled = false;
                            dojo.removeClass(btn, 'inactive');
                        });
                        this._showBoardMessage(
                            _('Your Turn'),
                            _('Choose a letter A, B, C or D to begin your turn.')
                        );
                    } else {
                        dojo.query('.letter-buttons button').forEach(function (btn) {
                            btn.disabled = true;
                        });
                    }
                    break;

                case 'answerQuestion':
                    // Modal is opened by questionRevealed notification
                    if (this.isCurrentPlayerActive()) {
                        // Ensure answer buttons are enabled for the active player
                        dojo.query('.answer-btn').forEach(function (btn) {
                            if (dojo.style(btn, 'display') !== 'none') {
                                btn.disabled = false;
                            }
                        });
                    } else {
                        this._showBoardMessage(
                            _('Question Time'),
                            _('Waiting for the active player to answer...')
                        );
                    }
                    break;

                case 'rollStaffDie':
                    if (this.isCurrentPlayerActive()) {
                        dojo.style(dojo.byId('staff-die'), 'display', 'inline-flex');
                        this._showBoardMessage(
                            _('Roll the Staff Die'),
                            _('Roll to check your Excellent staff bonus.')
                        );
                    }
                    break;

                case 'rollMovement':
                    if (this.isCurrentPlayerActive()) {
                        dojo.style(dojo.byId('move-die'), 'display', 'inline-flex');
                        this._showBoardMessage(
                            _('Roll for Movement'),
                            _('Roll the dice and move your pawn. You may also play Souper Duckats.')
                        );
                    }
                    break;

                case 'resolveSquare':
                    this._showBoardMessage(
                        _('Resolving Square'),
                        _('Please wait...')
                    );
                    break;

                case 'resolveRestaurant':
                    // Server-side automatic state — no player input required
                    this._showBoardMessage(
                        _('Restaurant Card'),
                        _('Resolving Restaurant card...')
                    );
                    break;

                case 'restaurantCardRoll':
                    if (this.isCurrentPlayerActive()) {
                        this._showBoardMessage(
                            _('Restaurant Card — Roll Dice'),
                            _('Roll the dice to resolve the card effect.')
                        );
                    }
                    break;

                case 'hireStaff':
                    if (this.isCurrentPlayerActive()) {
                        var hireArgs    = args && args.args && !Array.isArray(args.args) ? args.args : {};
                        // hireType and isHalfPrice from args.args (BGA sends [] not object)
                        // Fall back to gamedatas values set by getAllDatas() which are always current
                        var hireType    = hireArgs.hire_type  || (this.gamedatas ? this.gamedatas.hireType    : 'either') || 'either';
                        var isHalfPrice = hireArgs.half_price != null ? hireArgs.half_price
                                        : (this.gamedatas ? !!parseInt(this.gamedatas.hireHalfPrice, 10) : false);
                        try {
                            // Bug #22 — pass hireArgs (fresh staffBox/myStaff/duckats from
                            // argHireStaff) so the picker reads live data, not stale gamedatas.
                            this._showStaffPicker(hireType, isHalfPrice, hireArgs);
                        } catch(err) {
                            console.error('[DS] _showStaffPicker threw:', err);
                        }
                        this._showBoardMessage(
                            _('Hire Staff'),
                            _('Choose a staff member to hire, or pass.')
                        );
                    }
                    break;

                case 'helpWantedOffer':
                    // FR-2 — 2-player single-opponent offer. The opponent (multiactive)
                    // sees the standard staff picker with a MARKED-UP price (1.5x face).
                    if (this.isCurrentPlayerActive()) {
                        var offerArgs = args && args.args && !Array.isArray(args.args) ? args.args : {};
                        try {
                            this._showHelpWantedOfferPicker(offerArgs);
                        } catch (err) {
                            console.error('[DS] _showHelpWantedOfferPicker threw:', err);
                        }
                        this._showBoardMessage(
                            _('Help Wanted — Premium Offer'),
                            _('Hire this staff member at 1.5x value, or pass.')
                        );
                    }
                    break;

                case 'souperDuckatUse':
                    if (this.isCurrentPlayerActive()) {
                        this._setSouperDuckatBuyCashEnabled(false);
                        this._setSouperDuckatUseEnabled(true);
                        this._showBoardMessage(
                            _('Souper Duckats'),
                            _('Spend Souper Duckats for extra movement, or skip.')
                        );
                    }
                    break;

                case 'staffQuitsBid':
                case 'helpWantedBid':
                    // Multiactive state — all eligible players see the modal.
                    // Do not gate on isCurrentPlayerActive() here.
                    var auction = this.gamedatas.auction || null;
                    if (auction) {
                        this._showAuctionModal(stateName, auction);
                    }
                    break;

                case 'gameEnd':
                    this._closeQuestionModal();
                    this._showInnerBoard(true);
                    break;
            }
        },

        // ==============================================================
        // STATE: onLeavingState
        // ==============================================================

        onLeavingState: function (stateName) {
            console.log('Leaving state:', stateName);

            switch (stateName) {

                case 'chooseQuestion':
                    // Disable letter buttons when leaving this state
                    dojo.query('.letter-buttons button').forEach(function (btn) {
                        btn.disabled = true;
                    });
                    break;

                case 'rollStaffDie':
                    dojo.style(dojo.byId('staff-die'), 'display', 'none');
                    break;

                case 'rollMovement':
                    dojo.style(dojo.byId('move-die'), 'display', 'none');
                    break;

                case 'answerQuestion':
                    this._closeQuestionModal();
                    break;

                case 'hireStaff':
                    this._hideStaffPicker();
                    break;

                case 'helpWantedOffer':
                    this._hideStaffPicker();
                    break;

                case 'staffQuitsBid':
                case 'helpWantedBid':
                    this._hideAuctionModal();
                    break;

                case 'souperDuckatUse':
                    this._setSouperDuckatUseEnabled(false);
                    break;

                case 'chooseQuestion':
                case 'rollMovement':
                    this._setSouperDuckatBuyCashEnabled(false);
                    break;
            }
        },

        // ==============================================================
        // STATE: onUpdateActionButtons
        // Adds buttons to the BGA action status bar
        // ==============================================================

        onUpdateActionButtons: function (stateName, args) {
            console.log('onUpdateActionButtons:', stateName, args);

            if (this.isCurrentPlayerActive()) {
                switch (stateName) {

                    case 'rollStaffDie':
                        this.addActionButton('btn-roll-staff-die',
                            _('Roll Staff Die'), 'onRollStaffDie');
                        break;

                    case 'chooseQuestion':
                        // Enable Souper Duckat buy/cash panel pre-roll
                        this._setSouperDuckatBuyCashEnabled(true);
                        break;

                    case 'rollMovement':
                        this.addActionButton('btn-roll-movement',
                            _('Roll Movement Dice'), 'onRollMovement');
                        // Enable Souper Duckat buy/cash until dice are rolled
                        this._setSouperDuckatBuyCashEnabled(true);
                        break;

                    case 'souperDuckatUse':
                        this.addActionButton('btn-skip-souper',
                            _('Skip — Don\'t Spend'), 'onSkipSouperDuckats',
                            null, false, 'gray');
                        break;

                    case 'restaurantCardRoll':
                        this.addActionButton('btn-roll-for-card',
                            _('Roll Dice'), 'onRollForCard');
                        break;

                    case 'helpWantedBid':
                        this.addActionButton('btn-pass-bid',
                            _('Pass'), 'onPassBid', null, false, 'gray');
                        break;
                }
            }

            // Multi-active bid states — all eligible players get bid controls
            if (stateName === 'staffQuitsBid' || stateName === 'helpWantedBid') {
                if (this.isCurrentPlayerActive()) {
                    this.addActionButton('btn-place-bid',
                        _('Place Bid'), 'onPlaceBid');
                    this.addActionButton('btn-pass-bid-auction',
                        _('Pass'), 'onPassBid', null, false, 'gray');

                    // Open auction modal here — onUpdateActionButtons fires reliably
                    // after setup() and gamedatas is fully populated, unlike onEnteringState
                    // which may fire during log replay before gamedatas is ready.
                    if (!document.getElementById('ds-auction-overlay')) {
                        var auctionData = this.gamedatas ? this.gamedatas.auction : null;
                        if (auctionData) {
                            this._showAuctionModal(stateName, auctionData);
                        }
                    }
                }
            }
        },

        // ==============================================================
        // PLAYER ACTION HANDLERS
        // ==============================================================

        // --- Letter button click (A/B/C/D) ---
        _onLetterButton: function (evt) {
            dojo.stopEvent(evt);
            if (!this.checkAction('chooseLetter')) { return; }

            var letter = evt.currentTarget.id.replace('letter-', '').toUpperCase();

            this.bga.actions.performAction('chooseLetter', { letter: letter });
        },

        // --- Answer button click (inside question modal) ---
        _onAnswerButton: function (evt) {
            dojo.stopEvent(evt);
            if (!this.checkAction('submitAnswer')) { return; }

            var answer = evt.currentTarget.getAttribute('data-answer');

            // Visual feedback — mark selected
            dojo.query('.answer-btn').forEach(function (btn) {
                dojo.removeClass(btn, 'selected');
            });
            dojo.addClass(evt.currentTarget, 'selected');
            this._lockAnswerButtons();

            this.bga.actions.performAction('submitAnswer', { answer: answer });
        },

        // --- Staff Die button / action bar ---
        onRollStaffDie: function (evt) {
            if (evt) { dojo.stopEvent(evt); }
            if (!this.checkAction('rollStaffDie')) { return; }

            this.bga.actions.performAction('rollStaffDie', {});
        },

        // --- Movement dice button / action bar ---
        onRollMovement: function (evt) {
            if (evt) { dojo.stopEvent(evt); }
            if (!this.checkAction('rollMovement')) { return; }

            this.bga.actions.performAction('rollMovement', {});
        },

        // --- Skip Souper Duckats ---
        onSkipSouperDuckats: function (evt) {
            if (evt) { dojo.stopEvent(evt); }
            if (!this.checkAction('skipSouperDuckats')) { return; }
            this.bga.actions.performAction('skipSouperDuckats', {});
        },

        // --- Roll For Card (restaurant card dice roll) ---
        onRollForCard: function (evt) {
            if (evt) { dojo.stopEvent(evt); }
            if (!this.checkAction('rollForCard')) { return; }

            this.bga.actions.performAction('rollForCard', {});
        },


        // ==============================================================
        // AUCTION MODAL
        // ==============================================================

        _showAuctionModal: function(stateName, auction) {
            this._hideAuctionModal();

            if (!auction) {
                console.warn('[DuckSoup] No auction data available');
                return;
            }

            var staffType  = auction.staff_type || '';
            var staffValue = parseInt(auction.staff_value, 10) || 0;
            var currentBid = parseInt(auction.current_high_bid, 10) || 0;
            var source     = auction.source || 'help_wanted';
            var minBid     = currentBid > 0 ? currentBid + 1 : staffValue;

            var staffLabels = {
                'chef': 'Chef', 'sous_chef': 'Sous Chef', 'first_cook': 'First Cook',
                'cook': 'Cook', 'maitre_d': "Maitre d'", 'sommelier': 'Sommelier',
                'captain': 'Captain', 'server': 'Server'
            };
            var baseType   = staffType.replace(/_[0-9]+$/, '');
            var staffLabel = staffLabels[baseType] || staffType;

            var isQuits    = source === 'staff_quits';
            var title      = isQuits ? 'Staff Quits!' : 'Help Wanted';
            var subtitle   = isQuits
                ? staffLabel + ' has quit! Other players may bid to hire them.'
                : staffLabel + ' is available from the Staff Box. Value: ' + staffValue + ' Duckats.';

            var myDuckats  = this.gamedatas.players[this.player_id]
                ? parseInt(this.gamedatas.players[this.player_id].duckats, 10) || 0
                : 0;
            var canAfford  = myDuckats >= minBid;

            var bidStatus  = currentBid > 0
                ? 'Current high bid: ' + currentBid + ' Duckats. Minimum next bid: ' + minBid + ' Duckats.'
                : 'Opening bid: ' + staffValue + ' Duckats (staff value). You have ' + myDuckats + ' Duckats.';

            var bidRowHtml = canAfford
                ? '<div class="ds-auction-input-row">'
                  + '<button id="ds-bid-minus" class="ds-sd-counter-btn" type="button">-</button>'
                  + '<span id="ds-bid-amount" class="ds-auction-bid-amount">' + minBid + '</span>'
                  + '<button id="ds-bid-plus" class="ds-sd-counter-btn" type="button">+</button>'
                  + '<span class="ds-auction-duckat-label"> Duckats</span>'
                  + '</div>'
                  + '<button id="ds-bid-confirm" class="ds-btn ds-btn--use" type="button">Place Bid</button>'
                : '<p class="ds-auction-cant-afford">You cannot afford the minimum bid of ' + minBid + ' Duckats.</p>';

            var html = '<div id="ds-auction-overlay" class="ds-modal-overlay" role="dialog"'
                + ' style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;">'
                + '<div class="ds-modal ds-auction-modal" style="background:#fff;border-radius:8px;padding:24px;max-width:500px;width:90%;position:relative;">'
                + '<div class="ds-modal-header">'
                + '<h2 class="ds-modal-title">' + title + '</h2>'
                + '<p class="ds-modal-subtitle">' + subtitle + '</p>'
                + '</div>'
                + '<div class="ds-modal-body">'
                + '<p class="ds-auction-status">' + bidStatus + '</p>'
                + bidRowHtml
                + '</div>'
                + '<div class="ds-modal-footer">'
                + '<button id="ds-bid-pass" class="ds-btn ds-btn--secondary" type="button">Pass</button>'
                + '</div>'
                + '</div>'
                + '</div>';

            document.body.insertAdjacentHTML('beforeend', html);

            var self = this;
            var currentAmount = minBid;

            if (canAfford) {
                var amountEl = document.getElementById('ds-bid-amount');

                document.getElementById('ds-bid-minus').addEventListener('click', function() {
                    if (currentAmount > minBid) {
                        currentAmount = Math.max(minBid, currentAmount - 5);
                        amountEl.textContent = currentAmount;
                    }
                });

                document.getElementById('ds-bid-plus').addEventListener('click', function() {
                    if (currentAmount + 5 <= myDuckats) {
                        currentAmount += 5;
                        amountEl.textContent = currentAmount;
                    }
                });

                document.getElementById('ds-bid-confirm').addEventListener('click', function() {
                    if (!self.checkAction('placeBid')) { return; }
                    document.getElementById('ds-bid-confirm').disabled = true;
                    document.getElementById('ds-bid-pass').disabled = true;
                    self.bga.actions.performAction('placeBid', { amount: currentAmount })
                        .then(function() { self._hideAuctionModal(); })
                        .catch(function() {
                            document.getElementById('ds-bid-confirm') && (document.getElementById('ds-bid-confirm').disabled = false);
                            document.getElementById('ds-bid-pass') && (document.getElementById('ds-bid-pass').disabled = false);
                        });
                });
            }

            document.getElementById('ds-bid-pass').addEventListener('click', function() {
                if (!self.checkAction('passBid')) { return; }
                document.getElementById('ds-bid-pass').disabled = true;
                self.bga.actions.performAction('passBid', {})
                    .then(function() { self._hideAuctionModal(); })
                    .catch(function() {
                        document.getElementById('ds-bid-pass') && (document.getElementById('ds-bid-pass').disabled = false);
                    });
            });
        },

        _hideAuctionModal: function() {
            var el = document.getElementById('ds-auction-overlay');
            if (el) { el.remove(); }
        },

        // --- Place Bid (action bar button opens modal) ---
        onPlaceBid: function (evt) {
            if (evt) { dojo.stopEvent(evt); }
            // Always re-open — hide any stale overlay first, then show fresh
            this._hideAuctionModal();
            this._showAuctionModal(
                this.gamedatas.gamestate.name,
                this.gamedatas.auction || null
            );
        },

        // --- Pass Bid ---
        onPassBid: function (evt) {
            if (evt) { dojo.stopEvent(evt); }
            if (!this.checkAction('passBid')) { return; }
            this.bga.actions.performAction('passBid', {})
                .then(() => { this._hideAuctionModal(); });
        },

        // --- Hire Staff (called from staff picker UI via _onStaffTileSelected) ---
        // staffType and staffValue are passed from the picker tile data attributes
        onHireStaff: function (staffType, staffValue) {
            if (!this.checkAction('hireStaff')) { return; }

            this.bga.actions.performAction('hireStaff', {
                staff_type:  staffType,
                staff_value: staffValue
            });
        },

        // ==============================================================
        // NOTIFICATION SETUP
        // ==============================================================

        setupNotifications: function () {
            console.log('Setting up notifications');

            var notifs = [
                ['letterChosen',          500],
                ['questionRevealed',      500],
                ['answerResult',          3000],
                ['staffDieRolled',        2000],
                ['pawnMoved',             1000],
                ['squareLanded',          1500],
                ['staffQuits',            2000],
                ['helpWanted',            2000],
                ['helpWantedAuction',     2000],   // Bug #6 — active player passed first-refusal
                ['helpWantedOfferMade',    2000],  // FR-2 — 2p single-opponent offer presented
                ['helpWantedOfferDeclined',2000],  // FR-2 — opponent declined
                ['helpWantedNoTaker',      2000],  // FR-2 — no valid taker, turn ends
                ['staffHired',            1500],
                ['staffTransferred',      1500],
                ['staffReturned',         1500],
                ['auctionResolved',       2000],
                ['bidPlaced',             1000],
                ['bidPassed',             500],
                ['souperDuckatPlayed',    500],
                ['restaurantCard',        2000],
                ['cardRollResult',        2000],
                ['souperDuckatUpdate',    500],
                ['souperDuckatUsed',      1000],
                ['duckatUpdate',          500],
                ['paymentRequired',       1000],
                ['playerSkipped',         1500],
                ['gameWon',               3000],
            ];

            notifs.forEach(dojo.hitch(this, function (pair) {
                dojo.subscribe(pair[0], this, 'notif_' + pair[0]);
                this.notifqueue.setSynchronous(pair[0], pair[1]);
            }));
        },

        // ==============================================================
        // NOTIFICATION HANDLERS
        // ==============================================================

        notif_letterChosen: function (notif) {
            console.log('notif_letterChosen', notif);
            // Disable letter buttons after a choice is made
            dojo.query('.letter-buttons button').forEach(function (btn) {
                btn.disabled = true;
            });
        },

        notif_questionRevealed: function (notif) {
            console.log('notif_questionRevealed', notif);
            // Only active player receives this — open the question modal
            if (notif.args.question) {
                this._openQuestionModal(notif.args.question);
            }
        },

        notif_answerResult: function (notif) {
            console.log('notif_answerResult', notif);

            var correct        = notif.args.correct;
            var correctAnswer  = notif.args.correct_answer;
            var answerText     = notif.args.answer_text || '';
            var duckats        = notif.args.duckats;
            var player_id      = notif.args.player_id;
            var playerDuckats  = notif.args.player_duckats;

            // Highlight correct/wrong answer buttons in modal
            dojo.query('.answer-btn').forEach(function (btn) {
                var letter = btn.getAttribute('data-answer');
                if (letter === correctAnswer) {
                    dojo.addClass(btn, 'correct');
                } else if (dojo.hasClass(btn, 'selected')) {
                    dojo.addClass(btn, 'wrong');
                }
            });

            // Show result on board
            if (correct) {
                this._showBoardMessage(
                    _('Correct!') + ' +' + duckats + ' ' + _('Duckats'),
                    answerText
                );
                // Update Duckat counter
                if (this.duckatCounters[player_id]) {
                    this.duckatCounters[player_id].setValue(playerDuckats);
                }
            } else {
                this._showBoardMessage(
                    _('Incorrect'),
                    _('The correct answer was: ') + correctAnswer + '. ' + answerText
                );
            }

            // Close modal after showing result
            setTimeout(dojo.hitch(this, function () {
                this._closeQuestionModal();
            }), 2500);
        },

        notif_staffDieRolled: function (notif) {
            console.log('notif_staffDieRolled', notif);

            var staffType  = notif.args.staff_type;
            var bonus      = notif.args.bonus;
            var player_id  = notif.args.player_id;
            var playerDuck = notif.args.player_duckats;

            var displayName = this.staffNames[staffType] || staffType;

            this._showBoardMessage(
                _('Staff Die: ') + displayName,
                bonus > 0
                    ? _('Bonus: ') + bonus + ' ' + _('Duckats collected!')
                    : _('No bonus this time.')
            );

            if (bonus > 0 && this.duckatCounters[player_id]) {
                this.duckatCounters[player_id].setValue(playerDuck);
            }
        },

        notif_pawnMoved: function (notif) {
            console.log('notif_pawnMoved', notif);

            var player_id   = notif.args.player_id;
            var position    = notif.args.position;
            var squareType  = notif.args.square_type;
            var player      = this.gamedatas.players[player_id];
            var color       = player ? player.color : 'ff0000';

            this._animatePawn(player_id, position, color);

            this._showBoardMessage(
                _('Moved to: ') + this._squareDisplayName(squareType),
                _('Dice total: ') + notif.args.total
            );
        },

        notif_squareLanded: function (notif) {
            console.log('notif_squareLanded', notif);

            var squareType  = notif.args.square_type;
            var playerDuck  = notif.args.player_duckats;
            var player_id   = notif.args.player_id;

            if (playerDuck !== undefined && this.duckatCounters[player_id]) {
                this.duckatCounters[player_id].setValue(playerDuck);
            }

            this._showBoardMessage(
                this._squareDisplayName(squareType),
                notif.args.roll
                    ? _('Roll: ') + notif.args.roll + ' × 5 = ' +
                      (notif.args.reward || notif.args.penalty) + ' ' + _('Duckats')
                    : ''
            );
        },

        notif_staffQuits: function (notif) {
            console.log('notif_staffQuits', notif);

            var player_id = notif.args.player_id;
            var staffType = notif.args.staff_type;

            // Store auction data for onEnteringState to consume
            if (this.gamedatas) {
                this.gamedatas.auction = notif.args;
            }

            // Remove staff tile from the player's board
            this._hideExcellentStaff(player_id, staffType);

            this._showBoardMessage(
                _('Staff Quits!'),
                (this.staffNames[staffType] || staffType) + _(' has quit. Bidding opens!')
            );
        },

        notif_helpWanted: function (notif) {
            console.log('notif_helpWanted', notif);

            // Store auction data for onEnteringState to consume
            if (this.gamedatas) {
                this.gamedatas.auction = notif.args;
            }

            this._showBoardMessage(
                _('Help Wanted!'),
                (this.staffNames[notif.args.staff_type] || notif.args.staff_type) +
                _(' is available — value: ') + notif.args.staff_value + _(' Duckats')
            );
        },

        notif_staffHired: function (notif) {
            console.log('notif_staffHired', notif);

            var player_id   = notif.args.player_id;
            var staffType   = notif.args.staff_type;
            var slotType    = notif.args.slot_type || staffType;  // specific numbered slot (e.g. cook_1)
            var playerDuck  = notif.args.player_duckats;

            // Bug #14 fix — mark the hired slot unavailable in gamedatas so the next
            // picker open reads the correct availability instead of stale page-load data.
            if (this.gamedatas && this.gamedatas.staffBox) {
                this.gamedatas.staffBox[slotType] = '0';
            }

            // Bug #14 fix (cont.) — mark the slot as owned in myStaff so ownedCount
            // and remainingSlots are correct for the current player on the next picker open.
            if (this.gamedatas && String(player_id) === String(this.player_id)) {
                if (!this.gamedatas.myStaff) { this.gamedatas.myStaff = {}; }
                this.gamedatas.myStaff[slotType] = '1';
            }

            // Bug #10 fix — reset hireHalfPrice and hireType in gamedatas so a
            // subsequent normal hire doesn't fall back to a stale half-price value.
            if (this.gamedatas) {
                this.gamedatas.hireHalfPrice = 0;
                this.gamedatas.hireType      = 'kitchen'; // safe default; argHireStaff overrides on next enter
            }

            this._showExcellentStaff(player_id, staffType);

            if (this.duckatCounters[player_id]) {
                this.duckatCounters[player_id].setValue(playerDuck);
            }

            this._showBoardMessage(
                _('Staff Hired!'),
                (this.staffNames[staffType] || staffType) + _(' joins the team!')
            );
        },

        notif_staffTransferred: function (notif) {
            console.log('notif_staffTransferred', notif);

            var player_id = notif.args.player_id;
            var staffType = notif.args.staff_type;

            // Show on new owner's board
            this._showExcellentStaff(player_id, staffType);

            this._showBoardMessage(
                _('Staff Transferred!'),
                (this.staffNames[staffType] || staffType) + _(' moves to a new restaurant.')
            );
        },

        notif_staffReturned: function (notif) {
            console.log('notif_staffReturned', notif);

            var player_id  = notif.args.player_id;
            var staffType  = notif.args.staff_type;
            var refund     = notif.args.refund;
            var playerDuck = notif.args.player_duckats;

            this._hideExcellentStaff(player_id, staffType);

            if (this.duckatCounters[player_id]) {
                this.duckatCounters[player_id].setValue(playerDuck);
            }

            this._showBoardMessage(
                _('Staff Returned'),
                (this.staffNames[staffType] || staffType) +
                _(' returned for ') + refund + _(' Duckats.')
            );
        },





        notif_bidPlaced: function (notif) {
            console.log('notif_bidPlaced', notif);

            // Bug #13 fix — update gamedatas.auction so modal refreshes with correct minimum bid.
            if (this.gamedatas.auction) {
                this.gamedatas.auction.current_high_bid    = notif.args.amount;
                this.gamedatas.auction.current_high_bidder = notif.args.player_id;
            }

            // Refresh the modal if it is open so all players see the new high bid.
            var overlay = document.getElementById('ds-auction-overlay');
            if (overlay && this.isCurrentPlayerActive()) {
                var stateName = this.gamedatas.gamestate ? this.gamedatas.gamestate.name : '';
                this._showAuctionModal(stateName, this.gamedatas.auction);
            } else if (overlay) {
                // Spectator/non-active: just update the status text
                var statusEl = document.querySelector('.ds-auction-status');
                if (statusEl) {
                    statusEl.textContent = notif.args.player_name + ' bids ' + notif.args.amount + ' Duckats.';
                }
            }
        },

        notif_bidPassed: function (notif) {
            console.log('notif_bidPassed', notif);
            var statusEl = document.querySelector('.ds-auction-status');
            if (statusEl) {
                statusEl.textContent = notif.args.player_name + ' passed.';
            }
        },

        // Bug #6 — active player passed Help Wanted first-refusal; auction now opens for others.
        notif_helpWantedAuction: function (notif) {
            console.log('notif_helpWantedAuction', notif);
            if (this.gamedatas) {
                this.gamedatas.auction = notif.args;
            }
        },

        // FR-2 — 2-player offer notifications. Log-only; the actual hire/duckat
        // update rides on the existing staffHired notif fired by hireFromBox.
        notif_helpWantedOfferMade: function (notif) {
            console.log('notif_helpWantedOfferMade', notif);
        },

        notif_helpWantedOfferDeclined: function (notif) {
            console.log('notif_helpWantedOfferDeclined', notif);
        },

        notif_helpWantedNoTaker: function (notif) {
            console.log('notif_helpWantedNoTaker', notif);
        },

        notif_auctionResolved: function (notif) {
            console.log('notif_auctionResolved', notif);

            var winnerId = notif.args.player_id;
            if (winnerId && this.duckatCounters[winnerId]) {
                // Counters will be refreshed by getAllDatas on next state
            }

            this._showBoardMessage(
                _('Auction Resolved'),
                notif.args.amount
                    ? (this.gamedatas.players[winnerId]
                        ? this.gamedatas.players[winnerId].name : '') +
                      _(' wins for ') + notif.args.amount + _(' Duckats.')
                    : _('No bids — staff returns to the box.')
            );
        },

        notif_bidPlaced: function (notif) {
            console.log('notif_bidPlaced', notif);
            // BGA log message handles display — no UI action needed
        },

        notif_bidPassed: function (notif) {
            console.log('notif_bidPassed', notif);
            // BGA log message handles display
        },

        notif_souperDuckatPlayed: function (notif) {
            console.log('notif_souperDuckatPlayed', notif);

            var player_id = notif.args.player_id;
            var remaining = notif.args.souper_duckats_left;

            if (this.souperDuckatCounters[player_id]) {
                this.souperDuckatCounters[player_id].setValue(remaining);
            }
        },

        // Map card_type to image filename in img/rest_cards/
        _restaurantCardImages: {
            'air_conditioning':           'Air Conditioning Breaks Down.png',
            'business_great':             'Business is Great final.jpg',
            'critic_corky':               'CRITIC - Corky Weinberg.png',
            'critic_olive':               'CRITIC - Olive McDoyle.png',
            'critic_riley':               'CRITIC - Riley Baker.png',
            'competitor_bankrupt':        'Competitor Goes Bankrupt.png',
            'convention':                 'Convention in Town.png',
            'dishwasher_breaks':          'Dishwasher Breaks Down.png',
            'chef_on_tv':                 'Ex Chef on TV.png',
            'food_costs_jump':            'Food Costs Jump.png',
            'go_back_one':                'Go Back One Square.png',
            'go_forward_one':             'Go Forward One Square.png',
            'go_to_next_hire_dining':     'Go to Next Hire Dining Room.png',
            'go_to_next_hire_either':     'Go to Next Hire Kitchen or Dining Room.png',
            'go_to_next_hire_kitchen':    'Go to Next Hire Kitchn.png',
            'go_to_next_staff_quits':     'Go to Next Staff Quits.png',
            'chef_cook_bonus':            'If You Have an Ex Chef.png',
            'maitre_d_bonus':             "If You Have an Ex Maitre d'.png",
            'mothers_day':                "Mother's Day.png",
            'plumbing_problems':          'Plumbing Problems.png',
            'renos_repairs':              'Renos & Repairs final.jpg',
            'road_construction':          'Road Construction.png',
            'shuffle_deck':               'Shuffle Deck.png',
            'smallware_costs':            'Smallware Costs.png',
            'theft_kitchen':              'Theft from Kitchen.png',
            'theft_wine':                 'Theft from Wine Cellar.png',
            'vacation':                   'Vacation - May 2008.jpg',
        },

        notif_restaurantCard: function (notif) {
            console.log('notif_restaurantCard', notif);

            var cardType    = notif.args.card_type || '';
            var description = notif.args.description || '';
            var amount      = notif.args.amount || 0;
            var effect      = notif.args.effect || '';

            // Build effect summary text
            var effectText = '';
            if (amount > 0 && effect === 'pay') {
                effectText = _('Pay ') + amount + _(' Duckats');
            } else if (amount > 0 && (effect === 'collect' || effect === 'all_collect')) {
                effectText = _('Collect ') + amount + _(' Duckats');
            } else if (effect === 'critic') {
                effectText = _('All players collect based on staff value');
            } else if (effect === 'all_roll_pay' || effect === 'roll_pay') {
                effectText = _('Roll dice — pay 5× roll');
            } else if (effect === 'roll_collect') {
                effectText = _('Roll dice — collect 5× roll');
            } else if (effect === 'movement') {
                effectText = _('Move to new square');
            } else if (effect === 'vacation') {
                effectText = _('Lose your next turn');
            } else if (effect === 'shuffle') {
                effectText = _('Deck reshuffled');
            } else if (effect === 'conditional_hire') {
                effectText = _('Special hire opportunity');
            }

            // Show card image as overlay on inner board
            this._showRestaurantCardOverlay(cardType, description, effectText);
        },

        _showRestaurantCardOverlay: function (cardType, description, effectText) {
            // Remove any existing overlay
            var existing = document.getElementById('ds-restaurant-card-overlay');
            if (existing) { existing.remove(); }

            var imgFile = this._restaurantCardImages[cardType] || null;
            var imgHtml = '';
            if (imgFile) {
                var imgUrl = g_gamethemeurl + 'img/rest_cards/' + encodeURIComponent(imgFile);
                imgHtml = '<img src="' + imgUrl + '" class="ds-restaurant-card-img" alt="' + dojo.string.substitute('${0}', [cardType]) + '" />';
            }

            var effectHtml = effectText
                ? '<p class="ds-restaurant-card-effect">' + effectText + '</p>'
                : '';

            var overlayHtml = '<div id="ds-restaurant-card-overlay" class="ds-restaurant-card-overlay">'
                + '<div class="ds-restaurant-card-modal">'
                + imgHtml
                + '<p class="ds-restaurant-card-desc">' + description + '</p>'
                + effectHtml
                + '<button id="ds-restaurant-card-close" class="ds-btn ds-btn--secondary">' + _('OK') + '</button>'
                + '</div>'
                + '</div>';

            document.body.insertAdjacentHTML('beforeend', overlayHtml);

            // Auto-dismiss after 4 seconds, or on OK click
            var self = this;
            var closeBtn = document.getElementById('ds-restaurant-card-close');
            var timer = setTimeout(function () {
                var el = document.getElementById('ds-restaurant-card-overlay');
                if (el) { el.remove(); }
            }, 4000);

            closeBtn.addEventListener('click', function () {
                clearTimeout(timer);
                var el = document.getElementById('ds-restaurant-card-overlay');
                if (el) { el.remove(); }
            });
        },

        notif_cardRollResult: function (notif) {
            console.log('notif_cardRollResult', notif);

            var amount    = notif.args.amount;
            var cardType  = notif.args.card_type;
            var allPlayers = notif.args.all_players;

            var msg = allPlayers
                ? _('All players affected: ') + amount + _(' Duckats')
                : amount + _(' Duckats');
            this._showBoardMessage(_('Card Roll Result'), msg);
        },

        notif_souperDuckatUpdate: function (notif) {
            console.log('notif_souperDuckatUpdate', notif);

            var player_id = notif.args.player_id;
            var duckats   = notif.args.duckats;
            var souper    = notif.args.souper_duckats;

            this._updateSouperDuckatCount(player_id, souper);
            this._updateDuckatCount(player_id, duckats);
        },

        notif_souperDuckatUsed: function (notif) {
            console.log('notif_souperDuckatUsed', notif);

            var player_id = notif.args.player_id;
            var souper    = notif.args.souper_duckats;
            this._updateSouperDuckatCount(player_id, souper);
        },

        notif_duckatUpdate: function (notif) {
            console.log('notif_duckatUpdate', notif);

            var player_id    = notif.args.player_id;
            var player_duckats = notif.args.player_duckats;
            this._updateDuckatCount(player_id, player_duckats);
        },

        notif_paymentRequired: function (notif) {
            console.log('notif_paymentRequired', notif);

            this._showBoardMessage(
                _('Payment Required'),
                _('You owe ') + notif.args.amount +
                _(' Duckats. Return Excellent staff to cover the cost.')
            );
        },

        notif_playerSkipped: function (notif) {
            console.log('notif_playerSkipped', notif);

            this._showBoardMessage(
                _('Vacation!'),
                notif.args.player_name + _(' is on vacation and loses their turn.')
            );
        },

        notif_gameWon: function (notif) {
            console.log('notif_gameWon', notif);

            this._showBoardMessage(
                _('Winner!'),
                notif.args.player_name +
                _(' has hired all 12 Excellent staff and wins Duck Soup!')
            );
        },

        // ==============================================================
        // UTILITY METHODS
        // ==============================================================

        _squareDisplayName: function (squareType) {
            var names = {
                'duck_soup':            _('Duck Soup!'),
                'business_great':       _('Business Is Great!'),
                'renos_repairs':        _('Renos & Repairs'),
                'restaurant':           _('Restaurant'),
                'bistro_help_wanted':   _('Help Wanted'),
                'staff_quits':          _('Staff Quits!'),
                'hire_kitchen':         _('Hire Kitchen Staff'),
                'hire_dining_room':     _('Hire Dining Room Staff'),
                'hire_kitchen_or_dining': _('Hire Kitchen or Dining Room Staff'),
                'vacation':             _('Vacation!')
            };
            return names[squareType] || squareType;
        },

        // ==============================================================
        // STAFF PICKER METHODS (merged from staff_picker_methods.js)
        // ==============================================================

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

_showStaffPicker: function(hireType, isHalfPrice, pickerArgs) {
    this._hideStaffPicker(); // Defensive — remove any stale picker

    const gamedatas    = this.gamedatas;
    // Bug #22 — prefer FRESH state args from argHireStaff (staffBox / myStaff / duckats)
    // over the page-load gamedatas snapshot, which goes stale after setup/any hire and
    // caused the picker to show every role "Already Hired". gamedatas is a defensive fallback.
    const args         = (pickerArgs && typeof pickerArgs === 'object' && !Array.isArray(pickerArgs))
                         ? pickerArgs : {};
    const myDuckats    = (args.duckats != null)
                         ? parseInt(args.duckats, 10)
                         : parseInt(gamedatas.players[this.player_id].duckats, 10);
    // Bug #26 — staffBox / myStaff are no longer used to GATE hireability (the server now
    // sends args.staffAvailability, a per-player open-slot map). Retained only as defensive
    // fallbacks and for any incidental reads; the authoritative availability is staffAvailability.
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
    // Bug #22 — prefer fresh args.half_price_type; gamedatas is a fallback.
    const halfPriceStaffType = isHalfPrice
        ? (args.half_price_type || gamedatas.halfPriceStaffType || null)
        : null;

    // Bug #6 — Help Wanted first-refusal: picker shows only the rolled staff tile.
    // Bug #22 — prefer fresh args.help_wanted_staff_type; gamedatas is a fallback.
    const helpWantedStaffType = args.help_wanted_staff_type || gamedatas.helpWantedStaffType || null;

    // Build modal HTML
    let sectionsHtml = '';
    pools.forEach(pool => {
        let tilesHtml = '';
        pool.items.forEach(staff => {

            // Skip if this is a half-price hire and doesn't match the restricted type
            if (halfPriceStaffType && staff.type !== halfPriceStaffType) {
                return;
            }
            // Bug #6 — Skip if this is a help_wanted first-refusal for a different staff type
            if (helpWantedStaffType && staff.type !== helpWantedStaffType) {
                return;
            }

            // Bug #26 — availability is now a PER-PLAYER question answered server-side.
            // args.staffAvailability[baseType] = how many slots of this role are still OPEN
            // for the active player (0 = they own them all → "Already Hired"). This replaces
            // the old global-box count and the client-side ownership math.
            const staffAvailability = args.staffAvailability || {};
            const openSlots  = (staffAvailability[staff.type] != null)
                             ? parseInt(staffAvailability[staff.type], 10)
                             : staff.slots; // defensive fallback: assume all open if map missing
            const ownedCount = staff.slots - openSlots; // for pip display (filled = owned)

            // Slot type passed to PHP on hire. The server re-resolves the exact open slot via
            // findAvailableSlot, so the base type is sufficient here.
            const firstAvailableSlotType = staff.type;

            const canHire        = openSlots > 0;

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
                     data-staff-type="${firstAvailableSlotType}"
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
        <style>
            #ds-staff-picker-overlay .ds-staff-section-label{font-weight:bold;margin:8px 0 4px;font-size:14px;}
            #ds-staff-picker-overlay .ds-staff-grid{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;}
            #ds-staff-picker-overlay .ds-staff-tile{border:2px solid #ccc;border-radius:6px;padding:10px;width:120px;text-align:center;cursor:default;font-size:12px;background:#f9f9f9;}
            #ds-staff-picker-overlay .ds-staff-tile--available{border-color:#4a90d9;background:#e8f4ff;cursor:pointer;}
            #ds-staff-picker-overlay .ds-staff-tile--available:hover{background:#d0e8ff;border-color:#2270c0;}
            #ds-staff-picker-overlay .ds-staff-tile--unavailable{opacity:0.5;}
            #ds-staff-picker-overlay .ds-staff-tile--unaffordable{opacity:0.65;border-color:#e07030;}
            #ds-staff-picker-overlay .ds-staff-tile--selected{background:#c0dff8;border-color:#1a5ca0;}
            #ds-staff-picker-overlay .ds-staff-label{font-weight:bold;margin-bottom:4px;}
            #ds-staff-picker-overlay .ds-staff-value{color:#333;font-size:11px;}
            #ds-staff-picker-overlay .ds-staff-overlay{font-size:10px;color:#900;font-weight:bold;margin-bottom:4px;}
            #ds-staff-picker-overlay .ds-modal-title{margin:0 0 4px;font-size:18px;}
            #ds-staff-picker-overlay .ds-modal-subtitle{margin:0 0 12px;color:#555;}
            #ds-staff-picker-overlay #ds-staff-picker-cancel{width:100%;padding:10px;background:#888;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;margin-top:8px;}
            #ds-staff-picker-overlay #ds-staff-picker-cancel:hover{background:#666;}
            #ds-staff-picker-overlay .ds-pip{display:inline-block;width:8px;height:8px;border-radius:50%;border:1px solid #888;margin:1px;}
            #ds-staff-picker-overlay .ds-pip--filled{background:#4a90d9;}
            #ds-staff-picker-overlay .ds-half-price-badge{font-size:10px;background:#e8a020;color:#fff;border-radius:3px;padding:1px 4px;margin-bottom:4px;}
        </style>
        <div id="ds-staff-picker-overlay" class="ds-modal-overlay" role="dialog"
             aria-modal="true" aria-label="Hire Staff"
             style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;overflow-y:auto;">
            <div class="ds-modal ds-staff-picker" style="background:#fff;border-radius:8px;padding:24px;max-width:700px;width:90%;max-height:90vh;overflow-y:auto;position:relative;">
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

    // DOM diagnostic — confirm overlay injected and visible
    var _dbgOverlay = document.getElementById('ds-staff-picker-overlay');
    if (_dbgOverlay) {
        var _dbgStyle = window.getComputedStyle(_dbgOverlay);
        console.log('[DS] picker overlay in DOM:', true,
            'display:', _dbgStyle.display,
            'visibility:', _dbgStyle.visibility,
            'z-index:', _dbgStyle.zIndex,
            'opacity:', _dbgStyle.opacity,
            'position:', _dbgStyle.position,
            'width:', _dbgStyle.width,
            'height:', _dbgStyle.height
        );
        console.log('[DS] sectionsHtml length:', sectionsHtml.length);
        console.log('[DS] overlay innerHTML snippet:', _dbgOverlay.innerHTML.substring(0, 300));
    } else {
        console.error('[DS] picker overlay NOT found in DOM after insert');
    }

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
// _showHelpWantedOfferPicker( offerArgs )   [FR-2]
// 2-player single-opponent offer. Shows ONE staff tile at the marked-up
// price (ceil(1.5x face), sent as args.offer_price). Accept fires
// hireHelpWantedOffer; decline fires passHelpWantedOffer. Reuses the same
// overlay id / CSS as _showStaffPicker so _hideStaffPicker and _trapFocus
// apply unchanged. Kept separate from _showStaffPicker to avoid disturbing
// the hire/half-price/first-refusal paths.
// =============================================================

_showHelpWantedOfferPicker: function(offerArgs) {
    this._hideStaffPicker(); // Defensive — remove any stale picker

    const gamedatas = this.gamedatas;
    const args      = (offerArgs && typeof offerArgs === 'object' && !Array.isArray(offerArgs))
                      ? offerArgs : {};

    const staffType  = args.help_wanted_staff_type || gamedatas.helpWantedStaffType || null;
    const offerPrice = (args.offer_price != null) ? parseInt(args.offer_price, 10) : null;
    const myDuckats  = (args.duckats != null)
                       ? parseInt(args.duckats, 10)
                       : parseInt(gamedatas.players[this.player_id].duckats, 10);

    // Look up the staff definition (label, base face value) from the static data.
    let staffDef = null;
    ['kitchen', 'dining_room'].forEach(loc => {
        (this._staffData[loc] || []).forEach(s => {
            if (s.type === staffType) staffDef = s;
        });
    });

    if (!staffDef || offerPrice == null) {
        console.error('[DS] offer picker: missing staffDef or offer_price', staffType, offerPrice);
        return;
    }

    // Per-player open-slot availability for THIS opponent (server-sent).
    const staffAvailability = args.staffAvailability || {};
    const openSlots  = (staffAvailability[staffDef.type] != null)
                     ? parseInt(staffAvailability[staffDef.type], 10)
                     : staffDef.slots;
    const ownedCount = staffDef.slots - openSlots;

    const canHire   = openSlots > 0;
    const canAfford = myDuckats >= offerPrice;

    let stateClass  = '';
    let overlayHtml = '';
    if (!canHire) {
        stateClass  = 'ds-staff-tile--unavailable';
        overlayHtml = '<div class="ds-staff-overlay ds-staff-overlay--hired">Already Hired</div>';
    } else if (!canAfford) {
        stateClass  = 'ds-staff-tile--unaffordable';
        overlayHtml = '<div class="ds-staff-overlay ds-staff-overlay--broke">Can\'t Afford</div>';
    } else {
        stateClass  = 'ds-staff-tile--available';
    }

    let pipsHtml = '';
    if (staffDef.slots > 1) {
        for (let i = 0; i < staffDef.slots; i++) {
            const filled = i < ownedCount ? 'ds-pip--filled' : 'ds-pip--empty';
            pipsHtml += `<span class="ds-pip ${filled}"></span>`;
        }
        pipsHtml = `<div class="ds-staff-pips">${pipsHtml}</div>`;
    }

    const clickable    = canHire && canAfford;
    const tileTabIndex = clickable ? 'tabindex="0"' : '';
    const tileRole     = clickable ? 'role="button"' : '';

    // Premium badge (mirrors half-price badge styling, marked up instead of down).
    const premiumBadge = '<div class="ds-premium-badge">1.5&times; Value</div>';

    const tileHtml = `
        <div class="ds-staff-tile ${stateClass}"
             data-staff-type="${staffDef.type}"
             data-staff-value="${offerPrice}"
             data-clickable="${clickable ? '1' : '0'}"
             ${tileTabIndex} ${tileRole}
             aria-label="${staffDef.label}, ${offerPrice} Duckats${!canHire ? ', already hired' : !canAfford ? ', cannot afford' : ''}">
            ${premiumBadge}
            ${overlayHtml}
            <div class="ds-staff-icon ds-staff-icon--${staffDef.type}"></div>
            <div class="ds-staff-label">${staffDef.label}</div>
            <div class="ds-staff-value">
                <span class="ds-staff-value--original">${staffDef.value}</span>
                <span class="ds-staff-value--display">${offerPrice}</span>
                <span class="ds-staff-value--unit">Duckats</span>
            </div>
            ${pipsHtml}
        </div>`;

    const sectionsHtml = `
        <div class="ds-staff-section">
            <h3 class="ds-staff-section-label">Available Staff (offered by opponent)</h3>
            <div class="ds-staff-grid">${tileHtml}</div>
        </div>`;

    const titleText    = 'Help Wanted — Premium Offer';
    const subtitleText = `You have <strong>${myDuckats}</strong> Duckats`;

    const modalHtml = `
        <style>
            #ds-staff-picker-overlay .ds-staff-section-label{font-weight:bold;margin:8px 0 4px;font-size:14px;}
            #ds-staff-picker-overlay .ds-staff-grid{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;}
            #ds-staff-picker-overlay .ds-staff-tile{border:2px solid #ccc;border-radius:6px;padding:10px;width:120px;text-align:center;cursor:default;font-size:12px;background:#f9f9f9;position:relative;}
            #ds-staff-picker-overlay .ds-staff-tile--available{border-color:#4a90d9;background:#e8f4ff;cursor:pointer;}
            #ds-staff-picker-overlay .ds-staff-tile--available:hover{background:#d0e8ff;border-color:#2270c0;}
            #ds-staff-picker-overlay .ds-staff-tile--unavailable{opacity:0.5;}
            #ds-staff-picker-overlay .ds-staff-tile--unaffordable{opacity:0.65;border-color:#e07030;}
            #ds-staff-picker-overlay .ds-staff-tile--selected{background:#c0dff8;border-color:#1a5ca0;}
            #ds-staff-picker-overlay .ds-staff-label{font-weight:bold;margin-bottom:4px;}
            #ds-staff-picker-overlay .ds-staff-value{color:#333;font-size:11px;}
            #ds-staff-picker-overlay .ds-staff-value--original{text-decoration:line-through;color:#999;margin-right:4px;}
            #ds-staff-picker-overlay .ds-staff-overlay{font-size:10px;color:#900;font-weight:bold;margin-bottom:4px;}
            #ds-staff-picker-overlay .ds-modal-title{margin:0 0 4px;font-size:18px;}
            #ds-staff-picker-overlay .ds-modal-subtitle{margin:0 0 12px;color:#555;}
            #ds-staff-picker-overlay #ds-staff-picker-cancel{width:100%;padding:10px;background:#888;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;margin-top:8px;}
            #ds-staff-picker-overlay #ds-staff-picker-cancel:hover{background:#666;}
            #ds-staff-picker-overlay .ds-pip{display:inline-block;width:8px;height:8px;border-radius:50%;border:1px solid #888;margin:1px;}
            #ds-staff-picker-overlay .ds-pip--filled{background:#4a90d9;}
            #ds-staff-picker-overlay .ds-premium-badge{font-size:10px;background:#b0561f;color:#fff;border-radius:3px;padding:1px 4px;margin-bottom:4px;}
        </style>
        <div id="ds-staff-picker-overlay" class="ds-modal-overlay" role="dialog"
             aria-modal="true" aria-label="Help Wanted Premium Offer"
             style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;overflow-y:auto;">
            <div class="ds-modal ds-staff-picker" style="background:#fff;border-radius:8px;padding:24px;max-width:700px;width:90%;max-height:90vh;overflow-y:auto;position:relative;">
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

    // Wire the single tile (if clickable)
    document.querySelectorAll('.ds-staff-tile[data-clickable="1"]').forEach(tile => {
        const handler = (e) => {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            this._onHelpWantedOfferAccept(tile);
        };
        tile.addEventListener('click',   handler);
        tile.addEventListener('keydown', handler);
    });

    // Wire pass/decline button
    document.getElementById('ds-staff-picker-cancel')
        .addEventListener('click', () => this._onHelpWantedOfferDecline());

    this._trapFocus(document.getElementById('ds-staff-picker-overlay'));
},

// =============================================================
// _onHelpWantedOfferAccept( tile )   [FR-2]
// Opponent accepts the 1.5x offer → hireHelpWantedOffer (no args; the
// server holds the staff type and marked-up price in game state).
// =============================================================

_onHelpWantedOfferAccept: function(tile) {
    if (!this.checkAction('hireHelpWantedOffer')) { return; }

    tile.classList.add('ds-staff-tile--selected');
    document.querySelectorAll('.ds-staff-tile[data-clickable="1"]').forEach(t => {
        t.setAttribute('data-clickable', '0');
    });
    document.getElementById('ds-staff-picker-cancel').disabled = true;

    this.bga.actions.performAction('hireHelpWantedOffer', {}).then(() => {
        this._hideStaffPicker();
    }).catch((err) => {
        console.error('[DuckSoup] hireHelpWantedOffer action failed:', err);
        tile.classList.remove('ds-staff-tile--selected');
        tile.setAttribute('data-clickable', '1');
        document.getElementById('ds-staff-picker-cancel').disabled = false;
    });
},

// =============================================================
// _onHelpWantedOfferDecline()   [FR-2]
// Opponent declines the 1.5x offer → passHelpWantedOffer. Turn ends.
// =============================================================

_onHelpWantedOfferDecline: function() {
    if (!this.checkAction('passHelpWantedOffer')) { return; }

    document.getElementById('ds-staff-picker-cancel').disabled = true;

    this.bga.actions.performAction('passHelpWantedOffer', {}).then(() => {
        this._hideStaffPicker();
    }).catch((err) => {
        console.error('[DuckSoup] passHelpWantedOffer action failed:', err);
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

        // ==============================================================
        // SOUPER DUCKAT PANEL METHODS (merged from souper_duckat_methods.js)
        // ==============================================================

        _buildSouperDuckatPanel: function(playerId, initialCount) {
    const isMe = (parseInt(playerId, 10) === parseInt(this.player_id, 10));

    // Target: the player header panel already built in setup()
    // Assumes a container with id="ds-player-header-{playerId}" exists.
    // Target: .player-header inside staff-board-{playerId}
    const boardEl  = document.getElementById('staff-board-' + playerId);
    const headerEl = boardEl ? boardEl.querySelector('.player-header') : null;
    if (!headerEl) {
        console.warn('[DuckSoup] Player header not found for player ' + playerId);
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

    var usePromise = this.bga.actions.performAction('useSouperDuckats', { quantity: qty });
    if (usePromise && typeof usePromise.then === 'function') {
        usePromise.then(() => {
            this._setSouperDuckatUseEnabled(false);
        }).catch((err) => {
            console.error('[DuckSoup] useSouperDuckats failed:', err);
            this._setUseControlsDisabled(playerId, false);
        });
    }
},

_onSouperDuckatSkip: function(playerId) {
    this._setUseControlsDisabled(playerId, true);

    var skipPromise = this.bga.actions.performAction('skipSouperDuckats', {});
    if (skipPromise && typeof skipPromise.then === 'function') {
        skipPromise.then(() => {
            this._setSouperDuckatUseEnabled(false);
        }).catch((err) => {
            console.error('[DuckSoup] skipSouperDuckats failed:', err);
            this._setUseControlsDisabled(playerId, false);
        });
    }
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

    // If player has no Souper Duckats, PHP stCheckSouperDuckats auto-transitions.
    // Do not fire skipSouperDuckats action here — this may run during state-entry
    // notification phase when performAction returns undefined and crashes.
    if (souperCount === 0) {
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
    // Try panel element first, fall back to header counter
    var panelEl = document.getElementById('ds-sd-count-' + playerId);
    if (panelEl) return parseInt(panelEl.textContent, 10) || 0;
    if (this.souperDuckatCounters && this.souperDuckatCounters[playerId]) {
        return this.souperDuckatCounters[playerId].getValue() || 0;
    }
    return 0;
},

_getDuckatCount: function(playerId) {
    // Reads from the existing Duckat counter element built in setup().
    // Adjust selector to match the actual element id used in your header.
    const el = document.getElementById('duckat-count-' + playerId);
    return el ? parseInt(el.textContent, 10) || 0 : 0;
},

// =============================================================
// NOTIFICATION HANDLER
// Updates both the Souper Duckat count pip and Duckat count
// when the server fires a souperDuckatUpdate notification.
// =============================================================

_updateSouperDuckatCount: function(playerId, newCount) {
    var count = parseInt(newCount, 10) || 0;

    // Update injected panel count
    var panelEl = document.getElementById('ds-sd-count-' + playerId);
    if (panelEl) panelEl.textContent = count;

    // Update existing header counter
    if (this.souperDuckatCounters && this.souperDuckatCounters[playerId]) {
        this.souperDuckatCounters[playerId].setValue(count);
    }
    var headerEl = document.getElementById('souper-duckat-count-' + playerId);
    if (headerEl) headerEl.textContent = count;

    // Re-evaluate button states if controls are currently visible
    var buyCashEl = document.getElementById('ds-sd-buycash-' + playerId);
    if (buyCashEl && !buyCashEl.classList.contains('ds-sd-controls--hidden')) {
        this._refreshBuyCashState(playerId);
    }
    var useEl = document.getElementById('ds-sd-use-' + playerId);
    if (useEl && !useEl.classList.contains('ds-sd-controls--hidden')) {
        this._refreshUseState(playerId);
    }
},

_updateDuckatCount: function(playerId, newCount) {
    // Updates the existing Duckat counter element in the player header.
    const el = document.getElementById('duckat-count-' + playerId);
    if (el) el.textContent = parseInt(newCount, 10) || 0;
    // Also update the BGA counter if it exists
    if (this.duckatCounters && this.duckatCounters[playerId]) {
        this.duckatCounters[playerId].setValue(parseInt(newCount, 10) || 0);
    }
}

    });
});
