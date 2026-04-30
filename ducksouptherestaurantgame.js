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
                // Left column (top → bottom): squares 27–35
                {l: 3,  t: 3},  {l: 3,  t: 12}, {l: 3,  t: 21},
                {l: 3,  t: 30}, {l: 3,  t: 39}, {l: 3,  t: 48},
                {l: 3,  t: 57}, {l: 3,  t: 66}, {l: 3,  t: 79}
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
                                        <img src="${g_gamethemeurl}img/staff-die.png" alt="Staff Die">
                                    </div>
                                    <span>Roll the<br>Staff Die</span>
                                </button>
                                <button id="move-die" style="display:none;">
                                    <div id="move-die-image">
                                        <img src="${g_gamethemeurl}img/movement-dice.png" alt="Movement Dice">
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
                                    <img class="board-img" src="${g_gamethemeurl}img/board.jpg" alt="Duck Soup Board">
                                    <div id="inner-board">
                                        <img src="${g_gamethemeurl}img/inner-board.png" alt="Duck Soup">
                                    </div>
                                    <div class="board-contents inactive">
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

            // Wire staff board arrow navigation
            dojo.connect(dojo.byId('left-arrow'),  'onclick', this, '_onLeftArrow');
            dojo.connect(dojo.byId('right-arrow'), 'onclick', this, '_onRightArrow');

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
            // Build player board HTML dynamically and inject into wrapper
            var color     = player.color || 'ff0000';
            var name      = player.name  || '';
            var duckats   = player.duckats        || 0;
            var souper    = player.souper_duckats || 0;

            var boardHtml = `
                <div id="staff-board-${player_id}" class="staff-board-panel" style="display:none;">
                    <div class="player-header">
                        <div class="clearfix">
                            <div class="player-name left player-${player_id}"
                                 style="background-color:#${color};">${name}</div>
                            <div class="player-stats right">
                                <div class="clearfix">
                                    <div class="left">
                                        <div class="clearfix super-duckat">
                                            <div class="value left" id="souper-duckat-count-${player_id}">${souper}</div>
                                            <div class="left">
                                                <img src="${g_gamethemeurl}img/super-duckats.png" alt="Souper Duckats">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="left">
                                        <div class="clearfix duckat">
                                            <div class="value left" id="duckat-count-${player_id}">${duckats}</div>
                                            <div class="left">
                                                <img src="${g_gamethemeurl}img/duckats.png" alt="Duckats">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="player-background player-${player_id}"
                             style="background-color:#${color};"></div>
                    </div>
                    <div class="staff-board-wrap">
                        <img class="staff-board-img" src="${g_gamethemeurl}img/staff-board.jpg" alt="Staff Board">
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

            // Apply Excellent staff tiles from gamedatas.staff
            for (var staff_id in this.gamedatas.staff) {
                var tile = this.gamedatas.staff[staff_id];
                if (tile.player_id == player_id && tile.is_excellent == 1) {
                    this._showExcellentStaff(player_id, tile.staff_type);
                }
            }
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

        _showExcellentStaff: function (player_id, staffType) {
            var slotIndex = this.staffSlotIndex[staffType];
            if (slotIndex === undefined) { return; }

            var panel = dojo.byId('staff-board-' + player_id);
            if (!panel) { return; }

            var slots = dojo.query('.grid-item', panel);
            if (slots[slotIndex]) {
                dojo.addClass(slots[slotIndex], 'excellent');
                dojo.removeClass(slots[slotIndex], 'hidden');
            }
        },

        _hideExcellentStaff: function (player_id, staffType) {
            var slotIndex = this.staffSlotIndex[staffType];
            if (slotIndex === undefined) { return; }

            var panel = dojo.byId('staff-board-' + player_id);
            if (!panel) { return; }

            var slots = dojo.query('.grid-item', panel);
            if (slots[slotIndex]) {
                dojo.removeClass(slots[slotIndex], 'excellent');
                dojo.addClass(slots[slotIndex], 'hidden');
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
                var colorName  = this.pawnColors[player_color] || 'red';
                if (pawnsLayer) {
                    pawnsLayer.insertAdjacentHTML('beforeend',
                        '<div class="pawn-token pawn-' + colorName + '" id="pawn-' + player_id + '">' +
                        '<img src="' + g_gamethemeurl + 'img/pawn-' + colorName + '.png" alt="pawn"></div>'
                    );
                    pawnEl = dojo.byId('pawn-' + player_id);
                }
            }
            var boardEl = dojo.byId('board');
            if (!pawnEl || !boardEl) { return; }

            var coords = this.squareCoords[position % 36];

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
                    // Nothing to do here except ensure non-active players see waiting state
                    if (!this.isCurrentPlayerActive()) {
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
                    if (this.isCurrentPlayerActive()) {
                        this._showBoardMessage(
                            _('Restaurant Card'),
                            _('Draw and resolve the top Restaurant card.')
                        );
                    }
                    break;

                case 'staffQuitsBid':
                case 'helpWantedBid':
                    this._showBoardMessage(
                        _('Bidding Open'),
                        _('Place your bid or pass.')
                    );
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

                    case 'rollMovement':
                        this.addActionButton('btn-roll-movement',
                            _('Roll Movement Dice'), 'onRollMovement');
                        this.addActionButton('btn-play-souper',
                            _('Play Souper Duckat (+1 square)'), 'onPlaySouperDuckat',
                            null, false, 'gray');
                        break;

                    case 'resolveRestaurant':
                        this.addActionButton('btn-resolve-restaurant',
                            _('Resolve Card'), 'onResolveRestaurantCard');
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

        // --- Play Souper Duckat ---
        onPlaySouperDuckat: function (evt) {
            if (evt) { dojo.stopEvent(evt); }
            if (!this.checkAction('rollMovement')) { return; }

            this.bga.actions.performAction('playSouperDuckat', { count: 1 });
        },

        // --- Resolve Restaurant Card ---
        onResolveRestaurantCard: function (evt) {
            if (evt) { dojo.stopEvent(evt); }
            if (!this.checkAction('resolveRestaurantCard')) { return; }

            this.bga.actions.performAction('resolveRestaurantCard', {});
        },

        // --- Place Bid ---
        onPlaceBid: function (evt) {
            if (evt) { dojo.stopEvent(evt); }
            if (!this.checkAction('placeBid')) { return; }

            // Prompt for bid amount
            var amount = parseInt(prompt(_('Enter your bid amount in Duckats:')), 10);
            if (isNaN(amount) || amount <= 0) { return; }

            this.bga.actions.performAction('placeBid', { amount: amount });
        },

        // --- Pass Bid ---
        onPassBid: function (evt) {
            if (evt) { dojo.stopEvent(evt); }
            if (!this.checkAction('passBid')) { return; }

            this.bga.actions.performAction('passBid', {});
        },

        // --- Hire Staff (called programmatically from hire square UI) ---
        onHireStaff: function (staffType) {
            if (!this.checkAction('hireStaff')) { return; }

            this.bga.actions.performAction('hireStaff', { staff_type: staffType });
        },

        // ==============================================================
        // NOTIFICATION SETUP
        // ==============================================================

        setupNotifications: function () {
            console.log('Setting up notifications');

            var notifs = [
                ['letterChosen',       500],
                ['questionRevealed',   500],
                ['answerResult',       3000],
                ['staffDieRolled',     2000],
                ['pawnMoved',          1000],
                ['squareLanded',       1500],
                ['staffQuits',         2000],
                ['helpWanted',         2000],
                ['staffHired',         1500],
                ['staffTransferred',   1500],
                ['staffReturned',      1500],
                ['auctionResolved',    2000],
                ['bidPlaced',          500],
                ['bidPassed',          500],
                ['souperDuckatPlayed', 500],
                ['restaurantCard',     2000],
                ['paymentRequired',    1000],
                ['playerSkipped',      1500],
                ['gameWon',            3000]
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

            // Remove staff tile from the player's board
            this._hideExcellentStaff(player_id, staffType);

            this._showBoardMessage(
                _('Staff Quits!'),
                (this.staffNames[staffType] || staffType) + _(' has quit. Bidding opens!')
            );
        },

        notif_helpWanted: function (notif) {
            console.log('notif_helpWanted', notif);

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
            var playerDuck  = notif.args.duckats;

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

        notif_restaurantCard: function (notif) {
            console.log('notif_restaurantCard', notif);

            var card   = notif.args.card;
            var effect = notif.args.effect;

            if (!card) {
                this._showBoardMessage(_('Restaurant Card'), _('No card available.'));
                return;
            }

            var effectText = effect > 0
                ? _('Collect ') + effect + _(' Duckats')
                : effect < 0
                    ? _('Pay ') + Math.abs(effect) + _(' Duckats')
                    : '';

            this._showBoardMessage(
                _('Restaurant Card'),
                (card.description || '') + (effectText ? ' — ' + effectText : '')
            );

            var player_id   = notif.args.player_id;
            var playerDuck  = notif.args.player_duckats;
            if (playerDuck !== undefined && this.duckatCounters[player_id]) {
                this.duckatCounters[player_id].setValue(playerDuck);
            }
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
        }

    });
});
