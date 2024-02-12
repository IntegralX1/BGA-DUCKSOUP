{OVERALL_GAME_HEADER}

<link href="https://fonts.googleapis.com/css2?family=Limelight&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">

<div class="container">
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
                        <div id="staff-die-image">
						</div> 
                        <span>Roll the<br>Staff Die</span>
                    </button>

                    <!-- MOVEMENT DICE-->
                    <button id="move-die">
                        <img src="./img/movement-dice.png">
                        <span>Roll for<br>Movement</span>
                    </button>

                </div>


                <div class="staff-board-container">

                    <!-- STAFF BOARD ARROWS -->
                    <button id="left-arrow" class="arrow"><span></span></button>
                    <button id="right-arrow" class="arrow"><span></span></button>

                    <div class="player-header">

                        <div class="clearfix">

                            <!-- PLAYER NAME -->
                            <div class="player-name left player1">
                                12312547894564552123
                            </div>

                            <!-- PLAYER STATS-->
                            <div class="player-stats right">
                                <div class="clearfix">
                                    <div class="left">

                                        <!-- SUPER DUCKATS -->
                                        <div class="clearfix super-duckat">
                                            <div class="value left">
                                                3
                                            </div>
                                            <img class="left" src="./img/super-duckats.png">
                                        </div>

                                    </div>


                                    <div class="left">

                                        <!-- DUCKATS -->
                                        <div class="clearfix duckat">
                                            <div class="value left">
                                                100
                                            </div>
                                            <img class="left" src="./img/duckats.png">
                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="player-background player1"></div>

                    </div>


                    <!-- STAFF BOARD -->
                    <img class="staff-board" src="./img/staff-board.jpg">
                </div>

            </div>

            <!-- LEFT CONTENT-->
            <div class="left-content">

                <div class="board-container">

                    <!-- DUCK IMAGE TO DISPLAY BY DEFAULT, HIDDEN WHEN CONTENT SHOWN -->
                    <img class="inner-board inactive" src="./img/inner-board.png">

                    <!-- WRITTEN CONTENT SHOWN IN MIDDLE OF BOARD, INACTIVE BY DEFAULT -->
                    <div class="board-contents active">
                        <h2>Lorem ipsum dolor sit</h2>
                        <p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Nullam feugiat, turpis at pulvinar
                            vulputate, erat libero tristique tellus, nec bibendum odio risus sit amet ante. Aliquam erat
                            volutpat. Nunc auctor.</p>
                    </div>

                    <!-- BOARD -->
                    <img class="board" src="./img/board.jpg">
                </div>
            </div>
        </div>

    </div>
</div>

<script type="text/javascript">

var boardDiv = dojo.byId('board'); // This gets the div where you want to display the image

// Create an img element
var imgElement = dojo.create('img', {
    src: 'https://doc.boardgamearena.com/images/e/ec/Board.jpg', // Set the source to the path of your image
    alt: 'Game Board' // A text description of the image
}, boardDiv);

// Javascript HTML templates

/*
// Example:
var jstpl_some_game_item='<div class="my_game_item" id="my_game_item_${MY_ITEM_ID}"></div>';

*/

</script>  

{OVERALL_GAME_FOOTER}
