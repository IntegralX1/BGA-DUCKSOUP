<?php
/**
 * Duck Soup: The Restaurant Game
 * states.inc.php — Full state machine definition
 *
 * State IDs:
 *   1   = gameSetup     (BGA reserved)
 *   2   = chooseQuestion
 *   3   = answerQuestion
 *   4   = rollStaffDie
 *   5   = rollMovement
 *   6   = resolveSquare      (server-side auto)
 *   7   = resolveRestaurant  (draw + resolve restaurant card)
 *   8   = restaurantCardRoll (player rolls dice for card effect)
 *   9   = hireStaff          (player picks staff to hire)
 *   10  = souperDuckatUse    (player optionally spends Souper Duckats post-move)
 *   11  = staffQuitsBid      (multi-active auction: staff quits)
 *   12  = helpWantedBid      (multi-active auction: help wanted, 3-4 players)
 *   13  = endTurn            (server-side auto)
 *   14  = helpWantedOffer    (2-player: single opponent take-it-or-leave-it at 1.5x face)
 *   15  = checkSouperDuckats (server-side auto: skip souperDuckatUse if 0 owned)
 *   99  = gameEnd            (BGA reserved)
 */

$machinestates = array(

    // ---------------------------------------------------------------
    // 1 — GAME SETUP (BGA reserved, do not modify)
    // ---------------------------------------------------------------
    1 => array(
        'name'              => 'gameSetup',
        'description'       => '',
        'type'              => 'manager',
        'action'            => 'stGameSetup',
        'transitions'       => array('' => 2),
    ),

    // ---------------------------------------------------------------
    // 2 — CHOOSE QUESTION
    // Active player picks a letter (A/B/C/D).
    // Souper Duckat buy/cash is available in this state (pre-roll).
    // ---------------------------------------------------------------
    2 => array(
        'name'              => 'chooseQuestion',
        'description'       => clienttranslate('${actplayer} must choose a letter: A, B, C or D'),
        'descriptionmyturn' => clienttranslate('Choose a letter: A, B, C or D — then buy or cash Souper Duckats if desired'),
        'type'              => 'activeplayer',
        'possibleactions'   => array(
            'chooseLetter',
            'buySouperDuckat',
            'cashSouperDuckat',
        ),
        'transitions'       => array(
            'toAnswer'       => 3,
            'toRollStaffDie' => 4,
        ),
    ),

    // ---------------------------------------------------------------
    // 3 — ANSWER QUESTION
    // Active player submits their answer.
    // ---------------------------------------------------------------
    3 => array(
        'name'              => 'answerQuestion',
        'description'       => clienttranslate('${actplayer} is answering a question'),
        'descriptionmyturn' => clienttranslate('Submit your answer'),
        'type'              => 'activeplayer',
        'possibleactions'   => array('submitAnswer'),
        'transitions'       => array(
            'toRollStaffDie' => 4,
        ),
    ),

    // ---------------------------------------------------------------
    // 4 — ROLL STAFF DIE
    // Active player rolls the 12-sided Staff Die.
    // ---------------------------------------------------------------
    4 => array(
        'name'              => 'rollStaffDie',
        'description'       => clienttranslate('${actplayer} must roll the Staff Die'),
        'descriptionmyturn' => clienttranslate('Roll the Staff Die'),
        'type'              => 'activeplayer',
        'possibleactions'   => array('rollStaffDie'),
        'transitions'       => array(
            'toRollMovement' => 5,
        ),
    ),

    // ---------------------------------------------------------------
    // 5 — ROLL MOVEMENT
    // Active player rolls 2d6 for movement.
    // Souper Duckat buy/cash is still available until dice are rolled.
    // ---------------------------------------------------------------
    5 => array(
        'name'              => 'rollMovement',
        'description'       => clienttranslate('${actplayer} must roll the dice to move'),
        'descriptionmyturn' => clienttranslate('Roll the dice to move — buy or cash Souper Duckats before rolling'),
        'type'              => 'activeplayer',
        'possibleactions'   => array(
            'rollMovement',
            'buySouperDuckat',
            'cashSouperDuckat',
        ),
        'transitions'       => array(
            'toCheckSouperDuckats' => 15,
        ),
    ),

    // ---------------------------------------------------------------
    // 6 — RESOLVE SQUARE (server-side automatic)
    // Server reads the square type and routes to the correct next state.
    // ---------------------------------------------------------------
    6 => array(
        'name'              => 'resolveSquare',
        'description'       => clienttranslate('Resolving square...'),
        'type'              => 'game',
        'action'            => 'stResolveSquare',
        'transitions'       => array(
            'toRestaurant'      => 7,
            'toHireStaff'       => 9,
            'toStaffQuits'      => 11,
            'toHelpWanted'      => 12,   // 3-4 players: bidding auction
            'toHelpWantedOffer' => 14,   // 2 players: single-opponent 1.5x offer
            'toEndTurn'         => 13,
        ),
    ),

    // ---------------------------------------------------------------
    // 7 — RESOLVE RESTAURANT CARD (server-side automatic)
    // Server draws and begins resolving the top Restaurant card.
    // Cards that need a dice roll transition to state 8.
    // Cards that need a hire transition to state 9.
    // All others resolve fully server-side and go to endTurn.
    // ---------------------------------------------------------------
    7 => array(
        'name'              => 'resolveRestaurant',
        'description'       => '',
        'type'              => 'game',
        'action'            => 'stResolveRestaurant',
        'transitions'       => array(
            'toCardRoll'    => 8,
            'toHireStaff'   => 9,
            'toEndTurn'     => 13,
        ),
    ),

    // ---------------------------------------------------------------
    // 8 — RESTAURANT CARD ROLL
    // Active player rolls dice for roll-based card effects:
    //   renos_repairs (card): pay 5× roll
    //   business_great (card): collect 5× roll
    //   food_costs_jump: all players pay 5× roll
    // ---------------------------------------------------------------
    8 => array(
        'name'              => 'restaurantCardRoll',
        'description'       => clienttranslate('${actplayer} must roll the dice for the Restaurant card effect'),
        'descriptionmyturn' => clienttranslate('Roll the dice for the card effect'),
        'type'              => 'activeplayer',
        'possibleactions'   => array('rollForCard'),
        'transitions'       => array(
            'toEndTurn' => 13,
        ),
    ),

    // ---------------------------------------------------------------
    // 9 — HIRE STAFF
    // Active player chooses which Excellent staff to hire (or passes).
    // Triggered by: Kitchen square, Dining Room square,
    //   Hire K or DR square, or Go-to-Next movement cards.
    //   Also used for half-price conditional hires (chef_cook_bonus,
    //   maitre_d_bonus restaurant cards).
    // ---------------------------------------------------------------
    9 => array(
        'name'              => 'hireStaff',
        'description'       => clienttranslate('${actplayer} may hire an Excellent staff member'),
        'descriptionmyturn' => clienttranslate('Choose a staff member to hire, or pass'),
        'type'              => 'activeplayer',
        "args" => "argHireStaff",
        'possibleactions'   => array('hireStaff', 'passHire'),
        'transitions'       => array(
            'toEndTurn'         => 13,
            'toHelpWanted'      => 12,   // Bug #6 — active passes Help Wanted first-refusal (3-4p auction)
            'toHelpWantedOffer' => 14,   // FR-2 — active passes/can't hire in a 2-player game
        ),
    ),

    // ---------------------------------------------------------------
    // 10 — SOUPER DUCKAT USE
    // Active player may spend Souper Duckats for extra movement squares,
    // after normal movement has resolved.
    // If player has 0 Souper Duckats, server auto-transitions past this.
    // ---------------------------------------------------------------
    10 => array(
        'name'              => 'souperDuckatUse',
        'description'       => clienttranslate('${actplayer} may spend Souper Duckats for extra movement'),
        'descriptionmyturn' => clienttranslate('Spend Souper Duckats for extra squares, or skip'),
        'type'              => 'activeplayer',
        'possibleactions'   => array('useSouperDuckats', 'skipSouperDuckats'),
        'transitions'       => array(
            'toResolveSquare' => 6,
            'toEndTurn'       => 13,
        ),
    ),

    // ---------------------------------------------------------------
    // 11 — STAFF QUITS BID (multi-active)
    // All players except the original owner may bid on the quit staff.
    // ---------------------------------------------------------------
    11 => array(
        'name'              => 'staffQuitsBid',
        'description'       => clienttranslate('Players may bid on the available staff member'),
        'descriptionmyturn' => clienttranslate('Place a bid or pass on this staff member'),
        'type'              => 'multipleactiveplayer',
        'possibleactions'   => array('placeBid', 'passBid'),
        'action'            => 'stStaffQuitsBid',
        'transitions'       => array(
            'toEndTurn' => 13,
        ),
    ),

    // ---------------------------------------------------------------
    // 12 — HELP WANTED BID (multi-active)
    // All players may bid if the active player declines first offer.
    // ---------------------------------------------------------------
    12 => array(
        'name'              => 'helpWantedBid',
        'description'       => clienttranslate('Players may bid on the available staff member'),
        'descriptionmyturn' => clienttranslate('Place a bid or pass on this staff member'),
        'type'              => 'multipleactiveplayer',
        'possibleactions'   => array('placeBid', 'passBid'),
        'action'            => 'stHelpWantedBid',
        'transitions'       => array(
            'toEndTurn' => 13,
        ),
    ),

    // ---------------------------------------------------------------
    // 13 — END TURN (server-side automatic)
    // Resolves auction, checks win condition, advances to next player.
    // ---------------------------------------------------------------
    13 => array(
        'name'              => 'endTurn',
        'description'       => clienttranslate('End of turn...'),
        'type'              => 'game',
        'action'            => 'stEndTurn',
        'transitions'       => array(
            'toChooseQuestion' => 2,
            'toGameEnd'        => 99,
        ),
    ),

    // ---------------------------------------------------------------
    // 14 — HELP WANTED OFFER (multi-active, 2-player games only)
    // FR-2: When the active player lands on Help Wanted and either cannot
    // hire the rolled staff (already owns all slots of that type) or passes,
    // the single opponent is offered that staff at ceil(1.5x face value) as a
    // take-it-or-leave-it hire (no bidding). The opponent sees the standard
    // staff picker with the marked-up price. stHelpWantedOffer activates only
    // the opponent; if they cannot afford the offer the server skips this state.
    // ---------------------------------------------------------------
    14 => array(
        'name'              => 'helpWantedOffer',
        'description'       => clienttranslate('${actplayer} may hire the available staff at a premium'),
        'descriptionmyturn' => clienttranslate('Hire this staff member at 1.5x value, or pass'),
        'type'              => 'multipleactiveplayer',
        'args'              => 'argHelpWantedOffer',
        'possibleactions'   => array('hireHelpWantedOffer', 'passHelpWantedOffer'),
        'action'            => 'stHelpWantedOffer',
        'transitions'       => array(
            'toEndTurn' => 13,
        ),
    ),

    // ---------------------------------------------------------------
    // 15 — CHECK SOUPER DUCKATS (server-side automatic)
    // Entry gate before souperDuckatUse (state 10). If the active player
    // owns 0 Souper Duckats, skip straight to resolveSquare (state 6) so
    // the turn never dead-ends waiting on a spend the player can't make.
    // Otherwise, hand off to souperDuckatUse for player input.
    // ---------------------------------------------------------------
    15 => array(
        'name'              => 'checkSouperDuckats',
        'description'       => '',
        'type'              => 'game',
        'action'            => 'stCheckSouperDuckats',
        'transitions'       => array(
            'toSouperDuckatUse' => 10,
            'toResolveSquare'   => 6,
        ),
    ),

    // ---------------------------------------------------------------
    // 99 — GAME END (BGA reserved, do not modify)
    // ---------------------------------------------------------------
    99 => array(
        'name'              => 'gameEnd',
        'description'       => clienttranslate('End of game'),
        'type'              => 'manager',
        'action'            => 'stGameEnd',
        'args'              => 'argGameEnd',
    ),
);
