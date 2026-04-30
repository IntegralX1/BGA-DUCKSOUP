<?php
/**
 * states.inc.php
 *
 * Duck Soup — Complete Game State Machine
 *
 * STATE MAP (matches diagram):
 *
 *  1  gameSetup          manager   → 2
 *  2  chooseQuestion     active    → 3 (answerQuestion) | 4 (rollStaffDie)
 *  3  answerQuestion     active    → 4
 *  4  rollStaffDie       active    → 5
 *  5  rollMovement       active    → 6
 *  6  resolveSquare      game      → 7 (staffQuits) | 8 (helpWanted) | 9 (endTurn)
 *                                  | 10 (restaurant card) | 5 (Duck Soup re-roll)
 *  7  staffQuitsBid      multiple  → 9
 *  8  helpWantedBid      multiple  → 9
 *  9  endTurn            game      → 2 (next player) | 99 (winner)
 * 10  resolveRestaurant  active    → 9
 * 99  gameEnd            manager
 */

$machinestates = array(

    // ---------------------------------------------------------------
    // 1 — Initial setup (BGA required, do not modify)
    // ---------------------------------------------------------------
    1 => array(
        'name'        => 'gameSetup',
        'description' => '',
        'type'        => 'manager',
        'action'      => 'stGameSetup',
        'transitions' => array('' => 2),
    ),

    // ---------------------------------------------------------------
    // 2 — Choose a letter (A/B/C/D) before the roll
    //     Active player selects a trivia letter each turn.
    //     The card read by the player to the left determines whether
    //     it maps to a question or a ROLL STAFF DIE! result.
    // ---------------------------------------------------------------
    2 => array(
        'name'             => 'chooseQuestion',
        'description'      => clienttranslate('${actplayer} must choose a letter: A, B, C or D'),
        'descriptionmyturn'=> clienttranslate('${you} must choose a letter: A, B, C or D'),
        'type'             => 'activeplayer',
        'possibleactions'  => array('chooseLetter'),
        'transitions'      => array(
            'toAnswer'       => 3,
            'toRollStaffDie' => 4,
        ),
    ),

    // ---------------------------------------------------------------
    // 3 — Answer the trivia question
    //     Active player submits their answer (A/B/C/D).
    //     Correct → collect Duckats; wrong → nothing.
    //     Always advances to rollStaffDie (we never skip it).
    // ---------------------------------------------------------------
    3 => array(
        'name'             => 'answerQuestion',
        'description'      => clienttranslate('${actplayer} must answer the question'),
        'descriptionmyturn'=> clienttranslate('${you} must answer the question'),
        'type'             => 'activeplayer',
        'possibleactions'  => array('submitAnswer'),
        'transitions'      => array(
            'toRollStaffDie' => 4,
        ),
    ),

    // ---------------------------------------------------------------
    // 4 — Roll the Staff Die (12-sided)
    //     If chosen letter was ROLL STAFF DIE!, this resolves bonus.
    //     If active player has the rolled staff, collect half value.
    //     Otherwise this is a no-op pass-through state.
    // ---------------------------------------------------------------
    4 => array(
        'name'             => 'rollStaffDie',
        'description'      => clienttranslate('${actplayer} must roll the Staff Die'),
        'descriptionmyturn'=> clienttranslate('${you} must roll the Staff Die'),
        'type'             => 'activeplayer',
        'possibleactions'  => array('rollStaffDie'),
        'transitions'      => array(
            'toRollMovement' => 5,
        ),
    ),

    // ---------------------------------------------------------------
    // 5 — Roll the movement dice (2×6-sided)
    //     Active player may also play Souper Duckats here for extra
    //     movement before confirming the roll.
    // ---------------------------------------------------------------
    5 => array(
        'name'             => 'rollMovement',
        'description'      => clienttranslate('${actplayer} must roll the movement dice'),
        'descriptionmyturn'=> clienttranslate('${you} must roll the movement dice'),
        'type'             => 'activeplayer',
        'possibleactions'  => array('rollMovement', 'playSouperDuckat'),
        'transitions'      => array(
            'toResolveSquare' => 6,
        ),
    ),

    // ---------------------------------------------------------------
    // 6 — Resolve the landed square (server-side game logic)
    //     No player action — the server reads the square type and
    //     routes to the correct follow-up state.
    //
    //     Square → transition:
    //       DUCK SOUP           → toRollMovement (5)   re-roll granted
    //       BUSINESS IS GREAT   → toEndTurn (9)        collect handled in action
    //       RENOS AND REPAIRS   → toEndTurn (9)        pay handled in action
    //       VACATION            → toEndTurn (9)        flag set on player row
    //       RESTAURANT          → toRestaurant (10)    draw a card
    //       KITCHEN             → toEndTurn (9)        hire handled in resolveSquare
    //       DINING ROOM         → toEndTurn (9)
    //       HIRE K/DR           → toEndTurn (9)
    //       STAFF QUITS         → toStaffQuits (7)
    //       HELP WANTED         → toHelpWanted (8)
    // ---------------------------------------------------------------
    6 => array(
        'name'        => 'resolveSquare',
        'description' => '',
        'type'        => 'game',
        'action'      => 'stResolveSquare',
        'transitions' => array(
            'toRollMovement' => 5,
            'toRestaurant'   => 10,
            'toStaffQuits'   => 7,
            'toHelpWanted'   => 8,
            'toEndTurn'      => 9,
        ),
    ),

    // ---------------------------------------------------------------
    // 7 — Staff Quits auction
    //     Multiple-active: all players except the original owner can
    //     bid. Clockwise from left of active player.
    //     Highest bid wins; tile transfers. No bids → tile to box.
    // ---------------------------------------------------------------
    7 => array(
        'name'             => 'staffQuitsBid',
        'description'      => clienttranslate('Players may bid on the staff member who just quit'),
        'descriptionmyturn'=> clienttranslate('${you} may place a bid or pass'),
        'type'             => 'multipleactiveplayer',
        'possibleactions'  => array('placeBid', 'passBid'),
        'action'           => 'stStaffQuitsBid',
        'transitions'      => array(
            'toEndTurn' => 9,
        ),
    ),

    // ---------------------------------------------------------------
    // 8 — Help Wanted hire / auction
    //     Active player gets first right to hire at face value.
    //     If they decline, other players may bid (same clockwise rule).
    // ---------------------------------------------------------------
    8 => array(
        'name'             => 'helpWantedBid',
        'description'      => clienttranslate('Players may hire or bid for the available staff member'),
        'descriptionmyturn'=> clienttranslate('${you} may hire, bid, or pass'),
        'type'             => 'multipleactiveplayer',
        'possibleactions'  => array('hireStaff', 'placeBid', 'passBid'),
        'action'           => 'stHelpWantedBid',
        'transitions'      => array(
            'toEndTurn' => 9,
        ),
    ),

    // ---------------------------------------------------------------
    // 9 — End of turn (server-side)
    //     Check win condition (all 12 excellent staff) → gameEnd.
    //     Otherwise advance to next non-vacation player → chooseQuestion.
    // ---------------------------------------------------------------
    9 => array(
        'name'                  => 'endTurn',
        'description'           => '',
        'type'                  => 'game',
        'action'                => 'stEndTurn',
        'updateGameProgression' => true,
        'transitions'           => array(
            'toChooseQuestion' => 2,
            'toGameEnd'        => 99,
        ),
    ),

    // ---------------------------------------------------------------
    // 10 — Resolve a Restaurant card
    //      Active player draws and resolves the top restaurant card.
    //      If they cannot pay, they may return excellent staff for
    //      half value to cover the cost.
    // ---------------------------------------------------------------
    10 => array(
        'name'             => 'resolveRestaurant',
        'description'      => clienttranslate('${actplayer} must resolve the Restaurant card'),
        'descriptionmyturn'=> clienttranslate('${you} must resolve the Restaurant card'),
        'type'             => 'activeplayer',
        'possibleactions'  => array('resolveRestaurantCard', 'returnStaffForPayment'),
        'transitions'      => array(
            'toEndTurn' => 9,
        ),
    ),

    // ---------------------------------------------------------------
    // 99 — Game end (BGA required, do not modify)
    // ---------------------------------------------------------------
    99 => array(
        'name'        => 'gameEnd',
        'description' => clienttranslate('End of game'),
        'type'        => 'manager',
        'action'      => 'stGameEnd',
        'args'        => 'argGameEnd',
    ),
);
