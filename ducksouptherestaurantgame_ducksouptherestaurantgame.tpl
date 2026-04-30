{OVERALL_GAME_HEADER}

<div class="container">
    <div class="clearfix">

        <!-- RIGHT CONTENT -->
        <div class="right-content">

            <!-- TRIVIA LETTER BUTTONS -->
            <div class="letter-buttons">
                <button id="letter-a">A</button>
                <button id="letter-b">B</button>
                <button id="letter-c">C</button>
                <button id="letter-d">D</button>
            </div>

            <!-- QUESTION MODAL -->
            <div id="question-modal" class="modal">
                <div class="modal-content">
                    <span class="close-button">&times;</span>
                    <p id="question-text">Question will appear here</p>
                    <div id="question-answers" class="question-answers">
                        <button class="answer-btn" data-answer="A" id="answer-a"></button>
                        <button class="answer-btn" data-answer="B" id="answer-b"></button>
                        <button class="answer-btn" data-answer="C" id="answer-c"></button>
                        <button class="answer-btn" data-answer="D" id="answer-d"></button>
                    </div>
                </div>
            </div>

            <!-- DICE BUTTONS -->
            <div class="dice-buttons">

                <!-- STAFF DIE -->
                <button id="staff-die">
                    <div id="staff-die-image">
                        <img src="{g_gamethemeurl}img/staff-die.png" alt="Staff Die">
                    </div>
                    <span>Roll the<br>Staff Die</span>
                </button>

                <!-- MOVEMENT DICE -->
                <button id="move-die">
                    <div id="move-die-image">
                        <img src="{g_gamethemeurl}img/movement-dice.png" alt="Movement Dice">
                    </div>
                    <span>Roll for<br>Movement</span>
                </button>

            </div>

            <!-- STAFF BOARD SECTION -->
            <div class="staff-board-container">

                <!-- STAFF BOARD NAVIGATION ARROWS -->
                <button id="left-arrow" class="arrow"><span></span></button>
                <button id="right-arrow" class="arrow"><span></span></button>

                <!-- BEGIN playerstats_block -->
                <div class="player-header">
                    <div class="clearfix">

                        <!-- PLAYER NAME -->
                        <div class="player-name left {PLAYER_ID}" style="background-color: #{PLAYER_COLOR};">
                            {PLAYER_NAME}
                        </div>

                        <!-- PLAYER STATS -->
                        <div class="player-stats right">
                            <div class="clearfix">

                                <div class="left">
                                    <!-- SOUPER DUCKATS -->
                                    <div class="clearfix super-duckat">
                                        <div class="value left" id="souper-duckat-count-{PLAYER_ID}">
                                            {PLAYER_SOUPER_DUCKATS}
                                        </div>
                                        <div class="left" id="super-duckat-image">
                                            <img src="{g_gamethemeurl}img/super-duckats.png" alt="Souper Duckats">
                                        </div>
                                    </div>
                                </div>

                                <div class="left">
                                    <!-- DUCKATS -->
                                    <div class="clearfix duckat">
                                        <div class="value left" id="duckat-count-{PLAYER_ID}">
                                            {PLAYER_DUCKATS}
                                        </div>
                                        <div class="left" id="duckats-image">
                                            <img src="{g_gamethemeurl}img/duckats.png" alt="Duckats">
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="player-background {PLAYER_ID}"></div>
                </div>
                <!-- END playerstats_block -->

                <!-- STAFF BOARD GRID -->
                <!-- BEGIN staffboard -->
                <div id="staff-board-{PLAYER_ID}" class="staff-board-panel">
                    <img class="staff-board-img" src="{g_gamethemeurl}img/staff-board.jpg" alt="Staff Board">
                    <div class="card-grid">
                        <div class="grid-item {HIDDEN_CONTENT_1}" id="ex-chef-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_2}" id="ex-sous-chef-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_3}" id="ex-first-cook-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_4}" id="ex-cook-1-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_5}" id="ex-cook-2-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_6}" id="ex-cook-3-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_7}" id="ex-maitre-d-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_8}" id="ex-sommelier-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_9}" id="ex-captain-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_10}" id="ex-server-1-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_11}" id="ex-server-2-{PLAYER_ID}"></div>
                        <div class="grid-item {HIDDEN_CONTENT_12}" id="ex-server-3-{PLAYER_ID}"></div>
                    </div>
                </div>
                <!-- END staffboard -->

            </div>
        </div>

        <!-- LEFT CONTENT — GAME BOARD -->
        <div class="left-content">
            <div id="board-container">

                <!-- BEGIN gameboard -->
                <div id="board">

                    <!-- BOARD IMAGE -->
                    <img class="board-img" src="{g_gamethemeurl}img/board.jpg" alt="Duck Soup Game Board">

                    <!-- INNER BOARD (duck logo, shown when no content active) -->
                    <div id="inner-board" class="{INBOARD_CONTENT_STATE}">
                        <img src="{g_gamethemeurl}img/inner-board.png" alt="Duck Soup">
                    </div>

                    <!-- BOARD CONTENT AREA (answer feedback, card text, etc.) -->
                    <div class="board-contents {BOARD_CONTENT_STATE}">
                        <h2>{ANSWER_MESSAGE}</h2>
                        <p>{ANSWER_INFO}</p>
                    </div>

                    <!-- PAWN LAYER — one div per player, positioned by JS -->
                    <!-- BEGIN pawns_block -->
                    <div class="pawn-token pawn-{PAWN_COLOR}" 
                         id="pawn-{PAWN_PLAYER_ID}" 
                         data-position="{PAWN_POSITION}">
                        <img src="{g_gamethemeurl}img/pawn-{PAWN_COLOR}.png" alt="Player pawn">
                    </div>
                    <!-- END pawns_block -->

                </div>
                <!-- END gameboard -->

            </div>
        </div>

    </div>
</div>

{OVERALL_GAME_FOOTER}
