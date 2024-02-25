{OVERALL_GAME_HEADER}

<link href="https://fonts.googleapis.com/css2?family=Limelight&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">


<div class="container">

</div>


    <div class="clearfix">  
        <!-- RIGHT CONTENT -->
        <div class="right-content">
            <!-- TRIVIA BUTTONS -->
            <div class="letter-buttons">
                <button id="letter-a">A</button>
                <button id="letter-b">B</button>
                <button id="letter-c">C</button>
                <button id="letter-d">D</button>
            </div>
            <!-- DICE BUTTONS-->
            <div class="dice-buttons">
                <!-- STAFF DIE-->
                <button id="staff-die">
                    <div id="staff-die-image"></div>
                    <span>Roll the<br>Staff Die</span>
                </button>

                <!-- MOVEMENT DICE-->
                <button id="move-die">
                    <div id="move-die-image"></div>
                    <span>Roll for<br>Movement</span>
                </button>
            </div>

                <div class="staff-board-container">

                    <div class="player-header">

                        <div class="clearfix">

                            <!-- PLAYER NAME -->
                            <div class="player-name left player1">
                                {PLAYERNAME}
                            </div>

                            <!-- PLAYER STATS-->
                            <div class="player-stats right">
                                <div class="clearfix">
                                    <div class="left">
                                        <!-- SUPER DUCKATS -->
                                        <div class="clearfix super-duckat">
                                            <div class="value left">
                                                {PLAYER-SDUCKATS}
                                            </div>
                                            <div class="left" id="super-duckat-image"></div>
                                        </div>
                                    </div>


                                    <div class="left">
                                        <!-- DUCKATS -->
                                        <div class="clearfix duckat">
                                            <div class="value left">
                                                {PLAYER-DUCKATS}
                                            </div>
                                            <div class="left" id="duckats-image"></div>
                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="player-background player1"></div>

                    </div>

                    <!-- STAFF BOARD -->
                    <!-- BEGIN staffboard -->
                    <!-- STAFF BOARD ARROWS -->
                    <button id="right-arrow" class="arrow"><span></span></button>
                    <button id="left-arrow" class="arrow"><span></span></button>

                    <div id="staff-board">
                        <div class="card-grid">
                            <div class="grid-item {HIDDEN-CONTENT-1}" id="ex-chef"></div>
                            <div class="grid-item {HIDDEN-CONTENT-2}" id="ex-sous-chef"></div>
                            <div class="grid-item {HIDDEN-CONTEN-3}" id="ex-first-cook"></div>
                            <div class="grid-item {HIDDEN-CONTENT-4}" id="ex-cook-1"></div>
                            <div class="grid-item {HIDDEN-CONTENT-5}" id="ex-cook-2"></div>
                            <div class="grid-item {HIDDEN-CONTENT-6}" id="ex-cook-3"></div>
                            <div class="grid-item {HIDDEN-CONTENT-7}" id="ex-maitre-d"></div>
                            <div class="grid-item {HIDDEN-CONTENT-8}" id="ex-sommelier"></div>
                            <div class="grid-item {HIDDEN-CONTENT-9}" id="ex-capitan"></div>
                            <div class="grid-item {HIDDEN-CONTENT-10}" id="ex-server-1"></div>
                            <div class="grid-item {HIDDEN-CONTENT-11}" id="ex-server-2"></div>
                            <div class="grid-item {HIDDEN-CONTENT-12}" id="ex-server-3"></div>
                        </div>
                    </div>
                    <!-- END staffboard -->
                </div>
        </div>

         
        <!-- LEFT CONTENT -->
        <div class="left-content">
            <div id="board-container">
                <!-- BOARD -->
                <!--BEGIN boardcontent-->
                <div id="board">
                    <!-- DUCK IMAGE TO DISPLAY BY DEFAULT, HIDDEN WHEN CONTENT SHOWN -->
                    <div id="inner-board" class="{INBOARD-CONTENT-STATE}"></div>
                        <!-- WRITTEN CONTENT SHOWN IN MIDDLE OF BOARD, INACTIVE BY DEFAULT -->
                        <div class="board-contents {BOARD-CONTENT-STATE}">
                            <h2>{ANSWER-MESSAGE}</h2>
                            <p>{ANSWER-INFO}</p>
                        </div>
                    </div>
                </div>
                <!-- END boardconent -->
            </div>    
        </div> 
    </div>


<script type="text/javascript">

// Javascript HTML templates

/*
// Example:
var jstpl_some_game_item='<div class="my_game_item" id="my_game_item_${MY_ITEM_ID}"></div>';

*/

</script>  

{OVERALL_GAME_FOOTER}
