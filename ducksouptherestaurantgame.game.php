<?php
/**
 * ducksouptherestaurantgame.game.php
 *
 * Duck Soup — The Restaurant Game
 * BGA implementation: Phase 3
 *
 * Phase 3 changes from Phase 2:
 *   - BOARD_SQUARES corrected to 32 squares (PD-confirmed authoritative map)
 *   - BOARD_SIZE corrected to 32
 *   - STAFF values corrected per restaurant sign back image
 *   - Staff types unified: cook / server (slot-based) replacing cook_1/cook_2/cook_3
 *   - Question seeder updated: DuckSoupQuestions::seedQuestions()
 *   - Restaurant cards seeded: DuckSoupRestaurantCards::seedCards()
 *   - Restaurant card handler: DuckSoupCardHandler::resolveRestaurantCard()
 *   - Souper Duckat flow: buy/cash pre-roll; use post-movement (new stSouperDuckatUse)
 *   - hireStaff: now uses state 9 (hireStaff) with hire_type context
 *   - passHire: new action for declining hire opportunity
 *   - buySouperDuckat / cashSouperDuckat: new actions
 *   - useSouperDuckats / skipSouperDuckats: new post-movement actions
 *   - rollForCard: new action for restaurant card dice rolls
 *   - stResolveRestaurant: full card handler dispatch replacing stub
 *   - zombieTurn: extended for all new states
 */

require_once('questions_seed.php');
require_once('restaurant_cards_seed.php');
require_once('restaurant_card_handler.php');

class ducksouptherestaurantgame extends Bga\GameFramework\Table
{
    // ------------------------------------------------------------------
    // STAFF DEFINITIONS
    // Values per restaurant sign back image (confirmed Phase 3).
    // cook and server use slot counts (up to 3 each) rather than
    // numbered suffixes in game logic. DB still stores cook_1/cook_2/cook_3
    // and server_1/server_2/server_3 for backward compatibility with
    // existing staff and staff_box rows.
    // ------------------------------------------------------------------
    const STAFF = array(
        // Kitchen
        array('type' => 'chef',       'location' => 'kitchen',     'value' => 90, 'slots' => 1),
        array('type' => 'sous_chef',  'location' => 'kitchen',     'value' => 60, 'slots' => 1),
        array('type' => 'first_cook', 'location' => 'kitchen',     'value' => 40, 'slots' => 1),
        array('type' => 'cook',       'location' => 'kitchen',     'value' => 20, 'slots' => 3),
        // Dining room
        array('type' => 'maitre_d',   'location' => 'dining_room', 'value' => 70, 'slots' => 1),
        array('type' => 'sommelier',  'location' => 'dining_room', 'value' => 50, 'slots' => 1),
        array('type' => 'captain',    'location' => 'dining_room', 'value' => 40, 'slots' => 1),
        array('type' => 'server',     'location' => 'dining_room', 'value' => 30, 'slots' => 3),
    );

    // ------------------------------------------------------------------
    // BOARD SQUARES — authoritative 32-square clockwise map
    // Position 0 = Duck Soup (bottom-left corner).
    // Confirmed by PD from physical board image.
    // ------------------------------------------------------------------
    const BOARD_SQUARES = array(
        0  => 'duck_soup',
        1  => 'restaurant',
        2  => 'staff_quits',
        3  => 'help_wanted',
        4  => 'restaurant',
        5  => 'staff_quits',
        6  => 'hire_dining_room',
        7  => 'restaurant',
        8  => 'vacation',
        9  => 'hire_kitchen',
        10 => 'restaurant',
        11 => 'help_wanted',
        12 => 'restaurant',
        13 => 'staff_quits',
        14 => 'hire_kitchen_dining',
        15 => 'restaurant',
        16 => 'business_great',
        17 => 'restaurant',
        18 => 'staff_quits',
        19 => 'help_wanted',
        20 => 'restaurant',
        21 => 'staff_quits',
        22 => 'hire_dining_room',
        23 => 'restaurant',
        24 => 'renos_repairs',
        25 => 'hire_kitchen',
        26 => 'restaurant',
        27 => 'help_wanted',
        28 => 'restaurant',
        29 => 'staff_quits',
        30 => 'hire_kitchen_dining',
        31 => 'restaurant',
    );

    const BOARD_SIZE        = 32;
    const STARTING_DUCKATS  = 300;
    const SOUPER_DUCKAT_BUY = 50;
    const SOUPER_DUCKAT_CASH= 25;
    const TOTAL_STAFF       = 12; // 12 unique excellent staff positions to win

    // Game state label IDs (must match __construct registration)
    const GS_CURRENT_QUESTION    = 10;
    const GS_CURRENT_LETTER      = 11;
    const GS_IS_STAFF_DIE        = 12;
    const GS_STAFF_DIE_RESULT    = 13;
    const GS_MOVEMENT_ROLL       = 14;
    const GS_SOUPER_DUCKATS_USED = 15;
    const GS_AUCTION_ID          = 16;
    const GS_TURN_NUMBER         = 17;
    const GS_HIRE_TYPE           = 18; // 'kitchen'|'dining_room'|'either' for hireStaff state
    const GS_HIRE_HALF_PRICE     = 19; // 1 = this hire is at half price (card bonus)
    const GS_HELP_WANTED_PENDING = 20; // 1 = hireStaff entered from help_wanted; passHire creates auction
    const GS_HELP_WANTED_VALUE   = 21; // face value of the help_wanted rolled staff (Duckats)
    const GS_HELP_WANTED_OFFER   = 22; // FR-2: 1 = 2-player single-opponent offer at 1.5x face (markup hire)
    // GS_CARD_TYPE removed — card type is a string, stored in game_state_text table
    // helpWantedStaffType is also a string — stored in game_state_text table

    function __construct()
    {
        parent::__construct();
        self::initGameStateLabels(array(
            'currentQuestion'   => self::GS_CURRENT_QUESTION,
            'currentLetter'     => self::GS_CURRENT_LETTER,
            'isStaffDieResult'  => self::GS_IS_STAFF_DIE,
            'staffDieResult'    => self::GS_STAFF_DIE_RESULT,
            'movementRoll'      => self::GS_MOVEMENT_ROLL,
            'souperDuckatsUsed' => self::GS_SOUPER_DUCKATS_USED,
            'auctionId'         => self::GS_AUCTION_ID,
            'turnNumber'        => self::GS_TURN_NUMBER,
            'hireType'          => self::GS_HIRE_TYPE,
            'hireHalfPrice'     => self::GS_HIRE_HALF_PRICE,
            'helpWantedPending' => self::GS_HELP_WANTED_PENDING,
            'helpWantedValue'   => self::GS_HELP_WANTED_VALUE,
            'helpWantedOffer'   => self::GS_HELP_WANTED_OFFER,
            // cardType and helpWantedStaffType stored in game_state_text (strings)
        ));
    }

    // ==================================================================
    // SETUP
    // ==================================================================

    protected function setupNewGame($players, $options = array())
    {
        $gameinfos      = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        // --- Create player rows ---
        $sql    = 'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ';
        $values = array();
        foreach ($players as $player_id => $player) {
            $color    = array_shift($default_colors);
            $values[] = "('{$player_id}','{$color}','{$player['player_canal']}','"
                      . addslashes($player['player_name']) . "','"
                      . addslashes($player['player_avatar']) . "')";
        }
        self::DbQuery($sql . implode(',', $values));
        self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();

        // --- Extend player rows with Duck Soup fields ---
        foreach ($players as $player_id => $player) {
            self::DbQuery("UPDATE player SET
                player_duckats         = " . self::STARTING_DUCKATS . ",
                player_souper_duckats  = 3,
                player_board_position  = 0,
                player_restaurant_name = '',
                player_on_vacation     = 0
                WHERE player_id = {$player_id}");
        }

        // --- Populate staff_box ---
        self::DbQuery("DELETE FROM staff_box");
        $staffValues = array();
        foreach (self::STAFF as $s) {
            $slots = $s['slots'];
            for ($i = 1; $i <= $slots; $i++) {
                $type          = $slots > 1 ? $s['type'] . '_' . $i : $s['type'];
                $staffValues[] = "('{$type}','{$s['location']}',{$s['value']},1)";
            }
        }
        self::DbQuery(
            'INSERT INTO staff_box (staff_type, staff_location, staff_value, available) VALUES '
            . implode(',', $staffValues)
        );

        // --- Create staff rows for each player (all Average to start) ---
        self::DbQuery("DELETE FROM staff");
        $playerStaff = array();
        foreach ($players as $player_id => $player) {
            foreach (self::STAFF as $s) {
                $slots = $s['slots'];
                for ($i = 1; $i <= $slots; $i++) {
                    $type          = $slots > 1 ? $s['type'] . '_' . $i : $s['type'];
                    $playerStaff[] = "({$player_id},'{$type}','{$s['location']}',0,{$s['value']})";
                }
            }
        }
        self::DbQuery(
            'INSERT INTO staff (player_id, staff_type, staff_location, is_excellent, staff_value) VALUES '
            . implode(',', $playerStaff)
        );

        // --- Each player hires 3 Excellent staff before play begins ---
        $this->setupInitialStaffHires($players);

        // --- Seed question deck (900 questions, shuffled) ---
        DuckSoupQuestions::seedQuestions($this);

        // --- Seed restaurant card deck (30 cards, shuffled) ---
        DuckSoupRestaurantCards::seedCards($this);

        // --- Init global state values ---
        self::setGameStateInitialValue('currentQuestion',   0);
        self::setGameStateInitialValue('currentLetter',     0);
        self::setGameStateInitialValue('isStaffDieResult',  0);
        self::setGameStateInitialValue('staffDieResult',   -1);
        self::setGameStateInitialValue('movementRoll',      0);
        self::setGameStateInitialValue('souperDuckatsUsed', 0);
        self::setGameStateInitialValue('auctionId',         0);
        self::setGameStateInitialValue('turnNumber',        1);
        self::setGameStateInitialValue('hireType',          0); // 0=kitchen,1=dining_room,2=either
        self::setGameStateInitialValue('hireHalfPrice',     0);
        self::setGameStateInitialValue('helpWantedPending', 0);
        self::setGameStateInitialValue('helpWantedValue',   0);
        self::setGameStateInitialValue('helpWantedOffer',   0);
        // cardType stored as string in game_state_text — reset for new game
        $this->setCardType('');
        $this->setHelpWantedStaffType('');

        // --- Init statistics ---
        self::initStat('table', 'totalRounds', 0);
        self::initStat('table', 'bankDuckats', 0);
        self::initStat('player', 'duckats',        0);
        self::initStat('player', 'souperDuckats',  0);
        self::initStat('player', 'excellentStaff', 0);
        self::initStat('player', 'normalStaff',    0);
        self::initStat('player', 'staffBids',      0);
        self::initStat('player', 'staffBidsWon',   0);

        $this->activeNextPlayer();
        return 2; // chooseQuestion
    }

    /**
     * Before regular play begins, each player hires 3 Excellent staff
     * by rolling the Staff Die in turn order. Must take what is rolled
     * unless already owned (re-roll for singles; cooks/servers stackable).
     */
    private function setupInitialStaffHires($players)
    {
        foreach (array_keys($players) as $player_id) {
            $hired    = 0;
            $attempts = 0;

            while ($hired < 3 && $attempts < 100) {
                $attempts++;
                $result   = $this->rollStaffDieInternal();
                $staffDef = $result['staff'];
                $type     = $staffDef['type'];
                $slots    = $staffDef['slots'];

                // For multi-slot types, find the next available numbered slot
                if ($slots > 1) {
                    $slotType = $this->findAvailableSlot($type, $player_id);
                    if ($slotType === null) continue; // all slots owned by this player
                } else {
                    $slotType = $type;
                    // Check if already excellent
                    $alreadyExcellent = (int) self::getUniqueValueFromDB(
                        "SELECT is_excellent FROM staff
                         WHERE player_id = {$player_id} AND staff_type = '{$slotType}'"
                    );
                    if ($alreadyExcellent) continue;
                }

                // Check box availability
                $available = (int) self::getUniqueValueFromDB(
                    "SELECT available FROM staff_box WHERE staff_type = '{$slotType}'"
                );
                if (!$available) continue;

                // Hire
                $cost = $staffDef['value'];
                self::DbQuery(
                    "UPDATE player SET player_duckats = player_duckats - {$cost}
                     WHERE player_id = {$player_id}"
                );
                self::DbQuery(
                    "UPDATE staff SET is_excellent = 1
                     WHERE player_id = {$player_id} AND staff_type = '{$slotType}'"
                );
                self::DbQuery(
                    "UPDATE staff_box SET available = 0 WHERE staff_type = '{$slotType}'"
                );
                $hired++;
            }
        }
    }

    // ==================================================================
    // GET ALL DATAS
    // ==================================================================

    protected function getAllDatas()
    {
        $result            = array();
        $current_player_id = self::getCurrentPlayerId();

        // Player info + Duck Soup fields
        $result['players'] = self::getCollectionFromDB(
            'SELECT player_id id, player_score score, player_color color,
                    player_name name, player_duckats duckats,
                    player_souper_duckats souper_duckats,
                    player_board_position board_position,
                    player_restaurant_name restaurant_name,
                    player_on_vacation on_vacation
             FROM player'
        );

        // Staff boards (all players — excellent status is public)
        $result['staff'] = self::getCollectionFromDB(
            'SELECT staff_id, player_id, staff_type, staff_location,
                    is_excellent, staff_value
             FROM staff
             ORDER BY player_id, staff_location, staff_value DESC'
        );

        // Staff box availability — 2-column query so BGA returns scalar available values (0/1)
        // JS picker reads parseInt(staffBox[slotKey], 10) — requires scalar, not row object
        $result['staffBox'] = self::getCollectionFromDB(
            'SELECT staff_type, available FROM staff_box',
            'staff_type'
        );

        // Current player's own staff (for picker affordability checks)
        $result['myStaff'] = self::getCollectionFromDB(
            "SELECT staff_type, is_excellent FROM staff
             WHERE player_id = {$current_player_id} AND is_excellent = 1",
            'staff_type'
        );

        // Hire context for hireStaff state
        $result['hireType']           = $this->decodeHireType((int) self::getGameStateValue('hireType'));
        $result['hireHalfPrice']      = (int) self::getGameStateValue('hireHalfPrice');
        $result['halfPriceStaffType'] = $result['hireHalfPrice']
            ? $this->getHalfPriceStaffType($this->getCardType())
            : null;
        // Bug #6 — help_wanted first-refusal context
        $result['helpWantedPending']   = (int) self::getGameStateValue('helpWantedPending');
        $result['helpWantedStaffType'] = $this->getHelpWantedStaffType();

        // Current question (reveal to active player only)
        $questionId = (int) self::getGameStateValue('currentQuestion');
        if ($questionId > 0) {
            $activePlayerId = self::getActivePlayerId();
            if ($current_player_id == $activePlayerId) {
                $result['currentQuestion'] = self::getObjectFromDB(
                    "SELECT question_id, duckats_value, category, question_text,
                             answer_a, answer_b, answer_c, answer_d
                      FROM question WHERE question_id = {$questionId}"
                );
            } else {
                $result['currentQuestion'] = array('question_id' => $questionId, 'hidden' => true);
            }
        }

        // Active auction
        $auctionId = (int) self::getGameStateValue('auctionId');
        if ($auctionId > 0) {
            $result['auction'] = self::getObjectFromDB(
                "SELECT * FROM auction WHERE auction_id = {$auctionId}"
            );
        }

        // Global state flags
        $result['isStaffDieResult']  = (int) self::getGameStateValue('isStaffDieResult');
        $result['staffDieResult']    = (int) self::getGameStateValue('staffDieResult');
        $result['movementRoll']      = (int) self::getGameStateValue('movementRoll');
        $result['souperDuckatsUsed'] = (int) self::getGameStateValue('souperDuckatsUsed');
        $result['boardSquares']      = self::BOARD_SQUARES;

        return $result;
    }

    // ==================================================================
    // GAME PROGRESSION
    // ==================================================================

    function getGameProgression()
    {
        $max = self::getUniqueValueFromDB(
            'SELECT MAX(cnt) FROM (
                SELECT COUNT(*) cnt FROM staff
                WHERE is_excellent = 1
                GROUP BY player_id
             ) sub'
        );
        return $max !== null ? (int) round(($max / self::TOTAL_STAFF) * 100) : 0;
    }

    // ==================================================================
    // PLAYER ACTIONS
    // ==================================================================

    /**
     * Action: chooseLetter
     * Active player selects A, B, C, or D.
     * Server draws the top question and resolves what the letter maps to.
     */
    function chooseLetter($letter)
    {
        self::checkAction('chooseLetter');
        $player_id = self::getActivePlayerId();

        if (!in_array($letter, array('A','B','C','D'))) {
            throw new BgaUserException(clienttranslate('Invalid letter choice.'));
        }

        $question = $this->drawNextQuestion();
        if ($question === null) {
            throw new BgaVisibleSystemException('Question deck is empty after reshuffle.');
        }

        self::setGameStateValue('currentQuestion', (int) $question['question_id']);
        self::setGameStateValue('currentLetter',   array_search($letter, array('A','B','C','D')));

        // One of the four letters randomly maps to ROLL STAFF DIE!
        $staffDieLetter = array('A','B','C','D')[bga_rand(0, 3)];
        $isStaffDie     = ($letter === $staffDieLetter) ? 1 : 0;
        self::setGameStateValue('isStaffDieResult', $isStaffDie);

        if ($isStaffDie) {
            self::notifyAllPlayers('letterChosen',
                clienttranslate('${player_name} chose ${letter} — Roll Staff Die!'),
                array(
                    'player_id'    => $player_id,
                    'player_name'  => self::getActivePlayerName(),
                    'letter'       => $letter,
                    'is_staff_die' => true,
                )
            );
            $this->gamestate->nextState('toRollStaffDie');
        } else {
            self::notifyPlayer($player_id, 'questionRevealed', '', array(
                'question' => $question,
            ));
            self::notifyAllPlayers('letterChosen',
                clienttranslate('${player_name} chose ${letter} — a question has been drawn'),
                array(
                    'player_id'     => $player_id,
                    'player_name'   => self::getActivePlayerName(),
                    'letter'        => $letter,
                    'is_staff_die'  => false,
                    'duckats_value' => (int) $question['duckats_value'],
                )
            );
            $this->gamestate->nextState('toAnswer');
        }
    }

    /**
     * Action: submitAnswer
     */
    function submitAnswer($answer)
    {
        self::checkAction('submitAnswer');
        $player_id = self::getActivePlayerId();

        if (!in_array($answer, array('A','B','C','D'))) {
            throw new BgaUserException(clienttranslate('Invalid answer.'));
        }

        $questionId = (int) self::getGameStateValue('currentQuestion');
        $question   = self::getObjectFromDB(
            "SELECT * FROM question WHERE question_id = {$questionId}"
        );
        if ($question === null) {
            throw new BgaVisibleSystemException('Could not load question for answer check.');
        }

        $correct      = ($answer === $question['correct_answer']);
        $duckatReward = $correct ? (int) $question['duckats_value'] : 0;

        if ($correct) {
            $this->adjustDuckats($player_id, $duckatReward);
        }

        self::notifyAllPlayers('answerResult',
            $correct
                ? clienttranslate('${player_name} answers correctly and collects ${duckats} Duckats!')
                : clienttranslate('${player_name} answers incorrectly. The correct answer was ${correct_answer}.'),
            array(
                'player_id'      => $player_id,
                'player_name'    => self::getActivePlayerName(),
                'answer'         => $answer,
                'correct'        => $correct,
                'correct_answer' => $question['correct_answer'],
                'answer_text'    => $question['answer_text'] ?? '',
                'duckats'        => $duckatReward,
                'player_duckats' => $this->getPlayerDuckats($player_id),
            )
        );

        $this->gamestate->nextState('toRollStaffDie');
    }

    /**
     * Action: rollStaffDie
     */
    function rollStaffDie()
    {
        self::checkAction('rollStaffDie');
        $player_id = self::getActivePlayerId();

        $result   = $this->rollStaffDieInternal();
        $staffDef = $result['staff'];
        $index    = $result['index'];

        self::setGameStateValue('staffDieResult', $index);

        $bonus = $this->calculateStaffDieBonus($player_id, $staffDef);
        if ($bonus > 0) {
            $this->adjustDuckats($player_id, $bonus);
        }

        self::notifyAllPlayers('staffDieRolled',
            $bonus > 0
                ? clienttranslate('${player_name} rolled ${staff_type} and collects ${bonus} Duckats bonus!')
                : clienttranslate('${player_name} rolled ${staff_type} — no bonus.'),
            array(
                'player_id'      => $player_id,
                'player_name'    => self::getActivePlayerName(),
                'staff_type'     => $staffDef['type'],
                'bonus'          => $bonus,
                'player_duckats' => $this->getPlayerDuckats($player_id),
            )
        );

        $this->gamestate->nextState('toRollMovement');
    }

    /**
     * Action: rollMovement
     * Rolls 2d6 and moves pawn. Souper Duckat use follows in stSouperDuckatUse.
     */
    function rollMovement()
    {
        self::checkAction('rollMovement');
        $player_id = self::getActivePlayerId();

        $die1 = bga_rand(1, 6);
        $die2 = bga_rand(1, 6);
        $roll = $die1 + $die2;

        self::setGameStateValue('movementRoll',      $roll);
        self::setGameStateValue('souperDuckatsUsed', 0);

        $currentPos = (int) self::getUniqueValueFromDB(
            "SELECT player_board_position FROM player WHERE player_id = {$player_id}"
        );
        $newPos = ($currentPos + $roll) % self::BOARD_SIZE;
        self::DbQuery(
            "UPDATE player SET player_board_position = {$newPos} WHERE player_id = {$player_id}"
        );

        self::notifyAllPlayers('pawnMoved',
            clienttranslate('${player_name} rolls ${die1} + ${die2} = ${total} and moves to square ${position}'),
            array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'die1'        => $die1,
                'die2'        => $die2,
                'total'       => $roll,
                'position'    => $newPos,
                'square_type' => $this->getBoardSquare($newPos),
            )
        );

        // Go to souperDuckatUse — player may spend Souper Duckats before square resolves
        // stSouperDuckatUse auto-transitions to resolveSquare if player has 0 Souper Duckats
        $this->gamestate->nextState('toSouperDuckatUse');
    }

    /**
     * Action: buySouperDuckat
     * Available in chooseQuestion and rollMovement states (pre-roll).
     */
    function buySouperDuckat()
    {
        self::checkAction('buySouperDuckat');
        $player_id = self::getActivePlayerId();

        $duckats = $this->getPlayerDuckats($player_id);
        if ($duckats < self::SOUPER_DUCKAT_BUY) {
            throw new BgaUserException(
                clienttranslate('You need 50 Duckats to buy a Souper Duckat.')
            );
        }

        self::DbQuery(
            "UPDATE player SET
                player_duckats        = player_duckats - " . self::SOUPER_DUCKAT_BUY . ",
                player_souper_duckats = player_souper_duckats + 1
             WHERE player_id = {$player_id}"
        );

        $newDuckats = $this->getPlayerDuckats($player_id);
        $newSouper  = (int) self::getUniqueValueFromDB(
            "SELECT player_souper_duckats FROM player WHERE player_id = {$player_id}"
        );

        self::notifyAllPlayers('souperDuckatUpdate',
            clienttranslate('${player_name} buys a Souper Duckat for 50 Duckats'),
            array(
                'player_id'      => $player_id,
                'player_name'    => self::getActivePlayerName(),
                'duckats'        => $newDuckats,
                'souper_duckats' => $newSouper,
            )
        );
        // No state transition — player continues in current state
    }

    /**
     * Action: cashSouperDuckat
     * Available in chooseQuestion and rollMovement states (pre-roll).
     */
    function cashSouperDuckat()
    {
        self::checkAction('cashSouperDuckat');
        $player_id = self::getActivePlayerId();

        $owned = (int) self::getUniqueValueFromDB(
            "SELECT player_souper_duckats FROM player WHERE player_id = {$player_id}"
        );
        if ($owned < 1) {
            throw new BgaUserException(
                clienttranslate('You have no Souper Duckats to cash in.')
            );
        }

        self::DbQuery(
            "UPDATE player SET
                player_duckats        = player_duckats + " . self::SOUPER_DUCKAT_CASH . ",
                player_souper_duckats = player_souper_duckats - 1
             WHERE player_id = {$player_id}"
        );

        $newDuckats = $this->getPlayerDuckats($player_id);
        $newSouper  = (int) self::getUniqueValueFromDB(
            "SELECT player_souper_duckats FROM player WHERE player_id = {$player_id}"
        );

        self::notifyAllPlayers('souperDuckatUpdate',
            clienttranslate('${player_name} cashes in a Souper Duckat for 25 Duckats'),
            array(
                'player_id'      => $player_id,
                'player_name'    => self::getActivePlayerName(),
                'duckats'        => $newDuckats,
                'souper_duckats' => $newSouper,
            )
        );
    }

    /**
     * Action: useSouperDuckats
     * Post-movement: spend N Souper Duckats to advance N extra squares.
     */
    function useSouperDuckats($quantity)
    {
        self::checkAction('useSouperDuckats');
        $player_id = self::getActivePlayerId();
        $quantity  = max(1, (int) $quantity);

        $owned = (int) self::getUniqueValueFromDB(
            "SELECT player_souper_duckats FROM player WHERE player_id = {$player_id}"
        );
        if ($quantity > $owned) {
            throw new BgaUserException(
                clienttranslate('You do not have enough Souper Duckats.')
            );
        }

        self::DbQuery(
            "UPDATE player SET player_souper_duckats = player_souper_duckats - {$quantity}
             WHERE player_id = {$player_id}"
        );

        // Advance pawn by $quantity squares
        $currentPos = (int) self::getUniqueValueFromDB(
            "SELECT player_board_position FROM player WHERE player_id = {$player_id}"
        );
        $newPos = ($currentPos + $quantity) % self::BOARD_SIZE;
        self::DbQuery(
            "UPDATE player SET player_board_position = {$newPos} WHERE player_id = {$player_id}"
        );

        self::setGameStateValue('souperDuckatsUsed', $quantity);

        $newSouper = (int) self::getUniqueValueFromDB(
            "SELECT player_souper_duckats FROM player WHERE player_id = {$player_id}"
        );

        self::notifyAllPlayers('souperDuckatUsed',
            clienttranslate('${player_name} spends ${quantity} Souper Duckat(s) and moves ${quantity} extra square(s)'),
            array(
                'player_id'      => $player_id,
                'player_name'    => self::getActivePlayerName(),
                'quantity'       => $quantity,
                'position'       => $newPos,
                'square_type'    => $this->getBoardSquare($newPos),
                'souper_duckats' => $newSouper,
            )
        );

        // Re-resolve the new square
        $this->gamestate->nextState('toResolveSquare');
    }

    /**
     * Action: skipSouperDuckats
     * Player declines to spend Souper Duckats post-movement.
     */
    function skipSouperDuckats()
    {
        self::checkAction('skipSouperDuckats');
        $this->gamestate->nextState('toResolveSquare');
    }

    /**
     * Action: rollForCard
     * Player rolls 2d6 for a restaurant card that requires a dice roll.
     * card_type stored in GS_CARD_TYPE determines the effect.
     */
    function rollForCard()
    {
        self::checkAction('rollForCard');
        $player_id = self::getActivePlayerId();

        $die1      = bga_rand(1, 6);
        $die2      = bga_rand(1, 6);
        $diceTotal = $die1 + $die2;
        $cardType  = $this->getCardType();
        $amount    = DuckSoupCardHandler::applyRollEffect(
            $this,
            $cardType,
            $diceTotal,
            $player_id
        );

        self::notifyAllPlayers('cardRollResult',
            clienttranslate('${player_name} rolls ${die1} + ${die2} = ${total} for the card effect'),
            array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'die1'        => $die1,
                'die2'        => $die2,
                'total'       => $diceTotal,
                'amount'      => $amount,
                'card_type'   => $cardType,
                'all_players' => in_array($cardType, array('food_costs_jump')),
            )
        );

        $this->gamestate->nextState('toEndTurn');
    }

    /**
     * Action: hireStaff
     * Player selects an Excellent staff member to hire.
     */
    function hireStaff($staffType, $staffValue)
    {
        self::checkAction('hireStaff');
        $player_id   = self::getActivePlayerId();
        $staffType   = addslashes($staffType);
        $staffValue  = (int) $staffValue;
        $isHalfPrice = (int) self::getGameStateValue('hireHalfPrice');

        // Validate staff type exists in our definitions (base type lookup)
        $staffDef = $this->getStaffDefByType($staffType);
        if ($staffDef === null) {
            throw new BgaUserException(clienttranslate('Invalid staff type.'));
        }

        // Validate box availability
        $available = (int) self::getUniqueValueFromDB(
            "SELECT available FROM staff_box WHERE staff_type = '{$staffType}'"
        );
        if (!$available) {
            throw new BgaUserException(clienttranslate('That staff member is not available.'));
        }

        // Validate cost
        $expectedCost = $isHalfPrice
            ? (int) floor($staffDef['value'] / 2)
            : $staffDef['value'];

        if ($staffValue !== $expectedCost) {
            throw new BgaUserException(clienttranslate('Unexpected hire cost — please refresh.'));
        }

        $duckats = $this->getPlayerDuckats($player_id);
        if ($duckats < $expectedCost) {
            throw new BgaUserException(clienttranslate('Not enough Duckats to hire this staff member.'));
        }

        $this->hireFromBox($staffType, $player_id, $expectedCost);

        // Reset hire context (including help_wanted first-refusal flag if set)
        self::setGameStateValue('hireHalfPrice',     0);
        self::setGameStateValue('hireType',          0);
        self::setGameStateValue('helpWantedPending', 0);
        self::setGameStateValue('helpWantedValue',   0);
        $this->setHelpWantedStaffType('');

        $this->gamestate->nextState('toEndTurn');
    }

    /**
     * Action: passHire
     * Player declines to hire on a hire square or card offer.
     */
    function passHire()
    {
        self::checkAction('passHire');

        // Bug #6 / #28 / FR-2 — if this was a help_wanted first-refusal, the active
        // player has now voluntarily declined. Offer the staff to the other player(s):
        // routeHelpWantedToOthers forks to a 3-4p auction or a 2p single-opponent
        // 1.5x offer. Same routing as the cannot-hire branch in initiateHelpWanted.
        $helpWantedPending = (int) self::getGameStateValue('helpWantedPending');
        if ($helpWantedPending) {
            $staffType  = $this->getHelpWantedStaffType();
            $staffValue = (int) self::getGameStateValue('helpWantedValue');

            self::notifyAllPlayers('helpWanted',
                clienttranslate('${player_name} passes on ${staff_type} — it is offered to the other player(s).'),
                array(
                    'player_id'   => self::getActivePlayerId(),
                    'player_name' => self::getActivePlayerName(),
                    'staff_type'  => $staffType,
                    'staff_value' => $staffValue,
                    'help_wanted' => true,
                )
            );

            $this->routeHelpWantedToOthers($staffType, $staffValue);
            return;
        }

        self::setGameStateValue('hireHalfPrice', 0);
        self::setGameStateValue('hireType', 0);
        $this->gamestate->nextState('toEndTurn');
    }

    /**
     * Action: returnStaffForPayment
     * Player returns an Excellent staff tile for half value to cover a Restaurant card debt.
     */
    function returnStaffForPayment($staffType)
    {
        self::checkAction('returnStaffForPayment');
        $player_id = self::getActivePlayerId();
        $staffType = addslashes($staffType);

        $staffDef = $this->getStaffDefByType($staffType);
        if ($staffDef === null) {
            throw new BgaUserException(clienttranslate('Invalid staff type.'));
        }

        $isExcellent = (int) self::getUniqueValueFromDB(
            "SELECT is_excellent FROM staff
             WHERE player_id = {$player_id} AND staff_type = '{$staffType}'"
        );
        if (!$isExcellent) {
            throw new BgaUserException(clienttranslate('You can only return Excellent staff.'));
        }

        $refund = (int) floor($staffDef['value'] / 2);

        self::DbQuery(
            "UPDATE staff SET is_excellent = 0
             WHERE player_id = {$player_id} AND staff_type = '{$staffType}'"
        );
        self::DbQuery(
            "UPDATE staff_box SET available = 1 WHERE staff_type = '{$staffType}'"
        );
        $this->adjustDuckats($player_id, $refund);

        self::notifyAllPlayers('staffReturned',
            clienttranslate('${player_name} returns ${staff_type} for ${refund} Duckats'),
            array(
                'player_id'      => $player_id,
                'player_name'    => self::getActivePlayerName(),
                'staff_type'     => $staffType,
                'refund'         => $refund,
                'player_duckats' => $this->getPlayerDuckats($player_id),
            )
        );
        // Stay in resolveRestaurant — player must fully cover the debt
    }

    /**
     * Action: placeBid
     */
    function placeBid($amount)
    {
        self::checkAction('placeBid');
        $player_id = self::getCurrentPlayerId();
        $amount    = (int) $amount;

        $auctionId = (int) self::getGameStateValue('auctionId');
        $auction   = self::getObjectFromDB(
            "SELECT * FROM auction WHERE auction_id = {$auctionId}"
        );

        if ($auction === null || $auction['status'] !== 'active') {
            throw new BgaUserException(clienttranslate('No active auction.'));
        }
        if ($amount <= (int) $auction['current_high_bid']) {
            throw new BgaUserException(clienttranslate('Your bid must be higher than the current bid.'));
        }
        if ($amount > $this->getPlayerDuckats($player_id)) {
            throw new BgaUserException(clienttranslate('You do not have enough Duckats.'));
        }

        // Check not on vacation
        $onVacation = (int) self::getUniqueValueFromDB(
            "SELECT player_on_vacation FROM player WHERE player_id = {$player_id}"
        );
        if ($onVacation) {
            throw new BgaUserException(clienttranslate('You cannot bid while on vacation.'));
        }

        self::DbQuery(
            "UPDATE auction SET
                current_high_bidder = {$player_id},
                current_high_bid    = {$amount}
             WHERE auction_id = {$auctionId}"
        );

        self::incStat(1, 'staffBids', $player_id);

        self::notifyAllPlayers('bidPlaced',
            clienttranslate('${player_name} bids ${amount} Duckats'),
            array(
                'player_id'   => $player_id,
                'player_name' => self::getPlayerNameById($player_id),
                'amount'      => $amount,
            )
        );

        // Bug #13 fix — bidder stays active so others may counter-bid.
        // Only deactivate (ending the auction) if this player is already the sole
        // remaining active player (all others have passed).
        $activePlayers = $this->gamestate->getActivePlayerList();
        $othersStillIn = array_filter($activePlayers, fn($pid) => (int)$pid !== (int)$player_id);
        if (empty($othersStillIn)) {
            $this->gamestate->setPlayerNonMultiactive($player_id, 'toEndTurn');
        }
    }

    /**
     * Action: passBid
     */
    function passBid()
    {
        self::checkAction('passBid');
        $player_id = self::getCurrentPlayerId();

        self::notifyAllPlayers('bidPassed',
            clienttranslate('${player_name} passes'),
            array(
                'player_id'   => $player_id,
                'player_name' => self::getPlayerNameById($player_id),
            )
        );

        // Bug #13/#15 fix — use 'toEndTurn' (not empty string) so BGA fires the
        // correct transition when the last player deactivates.
        $this->gamestate->setPlayerNonMultiactive($player_id, 'toEndTurn');

        // If only one player remains active they are the high bidder — end auction for them.
        $remaining = $this->gamestate->getActivePlayerList();
        if (count($remaining) === 1) {
            $this->gamestate->setPlayerNonMultiactive((int)$remaining[0], 'toEndTurn');
        }
    }

    // ==================================================================
    // GAME STATE ACTIONS (server-side, no player input required)
    // ==================================================================

    /**
     * stSouperDuckatUse (server-side entry check)
     * If the active player has 0 Souper Duckats, auto-skip to resolveSquare.
     * Called automatically when entering souperDuckatUse state.
     * Note: BGA calls this via 'action' key if state type were 'game',
     * but since it's 'activeplayer' we check in onEnteringState JS side.
     * Server-side safety: we also check here via a dedicated method that
     * can be called from zombieTurn.
     */
    function stCheckSouperDuckats()
    {
        $player_id = self::getActivePlayerId();
        $owned = (int) self::getUniqueValueFromDB(
            "SELECT player_souper_duckats FROM player WHERE player_id = {$player_id}"
        );
        if ($owned === 0) {
            $this->gamestate->nextState('toResolveSquare');
        }
        // If owned > 0, stay in souperDuckatUse for player input
    }

    /**
     * stResolveSquare
     * Reads the active player's square and routes to appropriate next state.
     * Also handles post-Souper Duckat re-resolution after extra movement.
     */
    function stResolveSquare()
    {
        $player_id  = self::getActivePlayerId();
        $position   = (int) self::getUniqueValueFromDB(
            "SELECT player_board_position FROM player WHERE player_id = {$player_id}"
        );
        $squareType = $this->getBoardSquare($position);

        switch ($squareType) {

            case 'duck_soup':
                self::DbQuery(
                    "UPDATE player SET player_souper_duckats = player_souper_duckats + 1
                     WHERE player_id = {$player_id}"
                );
                $newSouper = (int) self::getUniqueValueFromDB(
                    "SELECT player_souper_duckats FROM player WHERE player_id = {$player_id}"
                );
                self::notifyAllPlayers('squareLanded',
                    clienttranslate('${player_name} lands on Duck Soup! Collects a Souper Duckat and rolls again.'),
                    array(
                        'player_id'      => $player_id,
                        'player_name'    => self::getActivePlayerName(),
                        'square_type'    => $squareType,
                        'souper_duckats' => $newSouper,
                    )
                );
                // Roll again — go back to rollMovement
                $this->gamestate->nextState('toEndTurn'); // Will re-enter via rollMovement
                // Actually per rules player rolls again — route to rollMovement via endTurn
                // We use a special re-roll: transition straight to rollStaffDie is wrong.
                // Per rules: land on Duck Soup → collect Souper Duckat → roll again.
                // Roll again means back to rollMovement (skip question + staff die for re-roll).
                // End turn advances to next player — instead we route back to rollMovement
                // via a dedicated transition. Using toEndTurn here as the closest available
                // transition; stEndTurn will need a duck_soup re-roll flag if full re-roll
                // behaviour is required. Flagged for Phase 4 refinement.
                return;

            case 'business_great':
                $die1   = bga_rand(1, 6);
                $die2   = bga_rand(1, 6);
                $roll   = $die1 + $die2;
                $reward = $roll * 5;
                $this->adjustDuckats($player_id, $reward);
                self::notifyAllPlayers('squareLanded',
                    clienttranslate('${player_name} lands on Business Is Great! Rolls ${die1}+${die2} and collects ${reward} Duckats.'),
                    array(
                        'player_id'      => $player_id,
                        'player_name'    => self::getActivePlayerName(),
                        'square_type'    => $squareType,
                        'die1'           => $die1,
                        'die2'           => $die2,
                        'reward'         => $reward,
                        'player_duckats' => $this->getPlayerDuckats($player_id),
                    )
                );
                $this->gamestate->nextState('toEndTurn');
                break;

            case 'renos_repairs':
                $die1    = bga_rand(1, 6);
                $die2    = bga_rand(1, 6);
                $roll    = $die1 + $die2;
                $penalty = $roll * 5;
                $this->adjustDuckats($player_id, -$penalty);
                self::notifyAllPlayers('squareLanded',
                    clienttranslate('${player_name} lands on Renos & Repairs! Rolls ${die1}+${die2} and pays ${penalty} Duckats.'),
                    array(
                        'player_id'      => $player_id,
                        'player_name'    => self::getActivePlayerName(),
                        'square_type'    => $squareType,
                        'die1'           => $die1,
                        'die2'           => $die2,
                        'penalty'        => $penalty,
                        'player_duckats' => $this->getPlayerDuckats($player_id),
                    )
                );
                $this->gamestate->nextState('toEndTurn');
                break;

            case 'vacation':
                self::DbQuery(
                    "UPDATE player SET player_on_vacation = 1 WHERE player_id = {$player_id}"
                );
                self::notifyAllPlayers('squareLanded',
                    clienttranslate('${player_name} lands on Vacation and loses their next turn.'),
                    array(
                        'player_id'   => $player_id,
                        'player_name' => self::getActivePlayerName(),
                        'square_type' => $squareType,
                    )
                );
                $this->gamestate->nextState('toEndTurn');
                break;

            case 'restaurant':
                self::notifyAllPlayers('squareLanded',
                    clienttranslate('${player_name} lands on a Restaurant square and draws a card.'),
                    array(
                        'player_id'   => $player_id,
                        'player_name' => self::getActivePlayerName(),
                        'square_type' => $squareType,
                    )
                );
                $this->gamestate->nextState('toRestaurant');
                break;

            case 'staff_quits':
                $this->initiateStaffQuits($player_id);
                break;

            case 'help_wanted':
                $this->initiateHelpWanted($player_id);
                break;

            case 'hire_kitchen':
                self::notifyAllPlayers('squareLanded',
                    clienttranslate('${player_name} lands on a Kitchen square and may hire kitchen staff.'),
                    array(
                        'player_id'   => $player_id,
                        'player_name' => self::getActivePlayerName(),
                        'square_type' => $squareType,
                    )
                );
                self::setGameStateValue('hireType', 0); // 0 = kitchen
                self::setGameStateValue('hireHalfPrice', 0);
                $this->gamestate->nextState('toHireStaff');
                break;

            case 'hire_dining_room':
                self::notifyAllPlayers('squareLanded',
                    clienttranslate('${player_name} lands on a Dining Room square and may hire dining room staff.'),
                    array(
                        'player_id'   => $player_id,
                        'player_name' => self::getActivePlayerName(),
                        'square_type' => $squareType,
                    )
                );
                self::setGameStateValue('hireType', 1); // 1 = dining_room
                self::setGameStateValue('hireHalfPrice', 0);
                $this->gamestate->nextState('toHireStaff');
                break;

            case 'hire_kitchen_dining':
                self::notifyAllPlayers('squareLanded',
                    clienttranslate('${player_name} lands on a Hire Kitchen or Dining Room square.'),
                    array(
                        'player_id'   => $player_id,
                        'player_name' => self::getActivePlayerName(),
                        'square_type' => $squareType,
                    )
                );
                self::setGameStateValue('hireType', 2); // 2 = either
                self::setGameStateValue('hireHalfPrice', 0);
                $this->gamestate->nextState('toHireStaff');
                break;

            default:
                $this->gamestate->nextState('toEndTurn');
                break;
        }
    }

    /**
     * stResolveRestaurant
     * Server-side: draws top Restaurant card and dispatches effect.
     */
    function stResolveRestaurant()
    {
        $player_id = self::getActivePlayerId();
        $result    = DuckSoupCardHandler::resolveRestaurantCard($this, $player_id);

        self::notifyAllPlayers('restaurantCard',
            clienttranslate('${player_name} draws a Restaurant card: ${description}'),
            array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'card_type'   => $result['card_type'],
                'description' => $result['description'],
                'effect'      => $result['effect'],
                'amount'      => $result['amount'],
                'all_players' => $result['all_players'],
            )
        );

        // Notify Duckat updates
        if ($result['all_players'] && in_array($result['effect'], array('all_collect','critic'))) {
            foreach ($result['critic_payouts'] as $pid => $payout) {
                if ($payout > 0) {
                    self::notifyAllPlayers('duckatUpdate', '', array(
                        'player_id'      => $pid,
                        'player_duckats' => $this->getPlayerDuckats($pid),
                    ));
                }
            }
        } elseif (!$result['all_players'] && $result['amount'] !== 0) {
            self::notifyAllPlayers('duckatUpdate', '', array(
                'player_id'      => $player_id,
                'player_duckats' => $this->getPlayerDuckats($player_id),
            ));
        }

        // Handle movement cards — player has been moved, resolve new square
        if ($result['effect'] === 'movement' && $result['move_to_square'] !== null) {
            self::notifyAllPlayers('pawnMoved',
                clienttranslate('${player_name} moves to square ${position} from the card effect.'),
                array(
                    'player_id'   => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'position'    => $result['move_to_square'],
                    'square_type' => $this->getBoardSquare($result['move_to_square']),
                )
            );
            // Re-resolve the new square so the destination's effect fires
            // (Staff Quits, Help Wanted, Business Is Great, hire squares, etc.)
            $this->gamestate->nextState('toResolveSquare');
            return;
        }

        // Roll-based cards — store card_type and go to restaurantCardRoll
        if ($result['needs_roll']) {
            $this->setCardType($result['card_type']);
            $this->gamestate->nextState('toCardRoll');
            return;
        }

        // Conditional hire
        if ($result['needs_hire']) {
            self::setGameStateValue('hireHalfPrice', 1);
            $hireTypeCode = $result['hire_type'] === 'kitchen' ? 0 :
                           ($result['hire_type'] === 'dining_room' ? 1 : 2);
            self::setGameStateValue('hireType', $hireTypeCode);
            $this->setCardType($result['card_type']);
            $this->gamestate->nextState('toHireStaff');
            return;
        }

        $this->gamestate->nextState('toEndTurn');
    }

    /**
     * stStaffQuitsBid
     */
    function stStaffQuitsBid()
    {
        $auctionId     = (int) self::getGameStateValue('auctionId');
        $auction       = self::getObjectFromDB(
            "SELECT * FROM auction WHERE auction_id = {$auctionId}"
        );
        $originalOwner = (int) $auction['original_owner'];
        $allPlayers    = self::loadPlayersBasicInfos();

        $biddingPlayers = array();
        foreach ($allPlayers as $pid => $pinfo) {
            // Exclude original owner; exclude vacationing players
            $onVacation = (int) self::getUniqueValueFromDB(
                "SELECT player_on_vacation FROM player WHERE player_id = {$pid}"
            );
            if ((int) $pid !== $originalOwner && !$onVacation) {
                $biddingPlayers[] = (int) $pid;
            }
        }

        if (empty($biddingPlayers)) {
            self::DbQuery(
                "UPDATE auction SET status = 'no_takers' WHERE auction_id = {$auctionId}"
            );
            self::DbQuery(
                "UPDATE staff_box SET available = 1
                 WHERE staff_type = '{$auction['staff_type']}'"
            );
            $this->gamestate->nextState('toEndTurn');
            return;
        }

        // Old BGA framework: setAllPlayersMultiactive activates everyone,
        // then we immediately deactivate excluded players (original owner + vacationers).
        $this->gamestate->setAllPlayersMultiactive('toEndTurn');
        $allPlayers2 = self::loadPlayersBasicInfos();
        foreach ($allPlayers2 as $pid => $pinfo) {
            if (!in_array((int) $pid, $biddingPlayers)) {
                $this->gamestate->setPlayerNonMultiactive((int) $pid, 'toEndTurn');
            }
        }
    }

    /**
     * stHelpWantedBid
     */
    function stHelpWantedBid()
    {
        $activePlayerId = (int) self::getActivePlayerId();
        $allPlayers     = self::loadPlayersBasicInfos();
        $biddingPlayers = array();

        foreach ($allPlayers as $pid => $pinfo) {
            // Bug #28 — the active player forfeited by declining first refusal and
            // cannot bid on the staff they passed. Exclude them from the auction.
            if ((int) $pid === $activePlayerId) {
                continue;
            }
            $onVacation = (int) self::getUniqueValueFromDB(
                "SELECT player_on_vacation FROM player WHERE player_id = {$pid}"
            );
            if (!$onVacation) {
                $biddingPlayers[] = (int) $pid;
            }
        }

        if (empty($biddingPlayers)) {
            $this->gamestate->nextState('toEndTurn');
            return;
        }

        // Old BGA framework: setAllPlayersMultiactive activates everyone,
        // then deactivate vacationing players and the active (passing) player.
        $this->gamestate->setAllPlayersMultiactive('toEndTurn');
        $allPlayers2 = self::loadPlayersBasicInfos();
        foreach ($allPlayers2 as $pid => $pinfo) {
            if (!in_array((int) $pid, $biddingPlayers)) {
                $this->gamestate->setPlayerNonMultiactive((int) $pid, 'toEndTurn');
            }
        }
    }

    // ==================================================================
    // FR-2 — HELP WANTED OFFER (2-player single-opponent, state 14)
    // The one opponent is offered the rolled staff at ceil(1.5x face value)
    // via the standard staff picker with a marked-up price. Take-it-or-leave-it.
    // ==================================================================

    /**
     * stHelpWantedOffer — activate ONLY the single opponent (the non-active,
     * non-vacation player). If none qualifies, end the turn. routeHelpWantedToOthers
     * has already verified affordability/slot before routing here, but we re-guard.
     */
    function stHelpWantedOffer()
    {
        $activePlayerId = (int) self::getActivePlayerId();
        $opponentId     = null;

        foreach (self::loadPlayersBasicInfos() as $pid => $pinfo) {
            if ((int) $pid === $activePlayerId) {
                continue;
            }
            $onVacation = (int) self::getUniqueValueFromDB(
                "SELECT player_on_vacation FROM player WHERE player_id = {$pid}"
            );
            if (!$onVacation) {
                $opponentId = (int) $pid;
                break;
            }
        }

        if ($opponentId === null) {
            $this->gamestate->nextState('toEndTurn');
            return;
        }

        // Activate everyone, then deactivate all but the single opponent.
        $this->gamestate->setAllPlayersMultiactive('toEndTurn');
        foreach (self::loadPlayersBasicInfos() as $pid => $pinfo) {
            if ((int) $pid !== $opponentId) {
                $this->gamestate->setPlayerNonMultiactive((int) $pid, 'toEndTurn');
            }
        }
    }

    /**
     * argHelpWantedOffer — feed the opponent's staff picker. Mirrors argHireStaff
     * but flags markup mode and ships the already-computed offer price as the value.
     * duckats/staffAvailability are the OPPONENT's (the active multiactive player).
     */
    function argHelpWantedOffer()
    {
        $activePlayerId = (int) self::getActivePlayerId();
        $offerPlayerId  = null;
        foreach ($this->gamestate->getActivePlayerList() as $pid) {
            $offerPlayerId = (int) $pid;
            break;
        }
        if ($offerPlayerId === null) {
            $offerPlayerId = $activePlayerId; // fallback; should not happen
        }

        return array(
            'help_wanted_offer'      => true,
            'help_wanted_staff_type' => $this->getHelpWantedStaffType(),
            // Marked-up price the opponent pays (already ceil(1.5x) from routing).
            'offer_price'            => (int) self::getGameStateValue('helpWantedValue'),
            'duckats'                => $this->getPlayerDuckats($offerPlayerId),
            'staffAvailability'      => $this->getPlayerStaffAvailability($offerPlayerId),
        );
    }

    /**
     * Action hireHelpWantedOffer — the opponent accepts the 1.5x offer.
     * Callable only by the multiactive opponent in state 14.
     */
    function hireHelpWantedOffer()
    {
        self::checkAction('hireHelpWantedOffer');
        $playerId = (int) self::getCurrentPlayerId();

        $staffType  = $this->getHelpWantedStaffType();
        $offerPrice = (int) self::getGameStateValue('helpWantedValue');

        // Re-validate: markup mode active, opponent has an open slot and can afford it.
        if ((int) self::getGameStateValue('helpWantedOffer') !== 1) {
            throw new BgaUserException(self::_('This offer is no longer available.'));
        }
        $slot = $this->findAvailableSlot($staffType, $playerId);
        if ($slot === null) {
            throw new BgaUserException(self::_('You have no open slot for this staff member.'));
        }
        if ($this->getPlayerDuckats($playerId) < $offerPrice) {
            throw new BgaUserException(self::_('You cannot afford this staff member.'));
        }

        // hireFromBox handles slot resolution, payment, DB write and the staffHired notify.
        $this->hireFromBox($staffType, $playerId, $offerPrice);

        // Clear offer context and end the turn.
        self::setGameStateValue('helpWantedOffer', 0);
        self::setGameStateValue('helpWantedValue', 0);
        $this->setHelpWantedStaffType('');

        $this->gamestate->setPlayerNonMultiactive($playerId, 'toEndTurn');
    }

    /**
     * Action passHelpWantedOffer — the opponent declines the 1.5x offer.
     * Callable only by the multiactive opponent in state 14. Turn ends.
     */
    function passHelpWantedOffer()
    {
        self::checkAction('passHelpWantedOffer');
        $playerId = (int) self::getCurrentPlayerId();

        $staffType = $this->getHelpWantedStaffType();
        self::notifyAllPlayers('helpWantedOfferDeclined',
            clienttranslate('${player_name} declines ${staff_type}. The turn ends.'),
            array(
                'player_id'   => $playerId,
                'player_name' => self::getPlayerNameById($playerId),
                'staff_type'  => $staffType,
            )
        );

        self::setGameStateValue('helpWantedOffer', 0);
        self::setGameStateValue('helpWantedValue', 0);
        $this->setHelpWantedStaffType('');

        $this->gamestate->setPlayerNonMultiactive($playerId, 'toEndTurn');
    }

    /**
     * stEndTurn
     * Resolves active auction, checks win condition, advances to next player.
     */
    function stEndTurn()
    {
        $auctionId = (int) self::getGameStateValue('auctionId');

        if ($auctionId > 0) {
            $auction = self::getObjectFromDB(
                "SELECT * FROM auction WHERE auction_id = {$auctionId}"
            );

            if ($auction && $auction['status'] === 'active') {
                $winnerId  = (int) $auction['current_high_bidder'];
                $winAmount = (int) $auction['current_high_bid'];

                if ($winnerId > 0 && $winAmount > 0) {
                    $this->adjustDuckats($winnerId, -$winAmount);

                    // Transfer tile: for staff_quits the tile came from original_owner's board
                    if ($auction['source'] === 'staff_quits') {
                        $originalOwner = (int) $auction['original_owner'];
                        // Original owner's slot already marked average in initiateStaffQuits
                        // Find first available slot for this type on winner's board
                        $slotType = $this->findAvailableSlot($auction['staff_type'], $winnerId)
                                    ?? $auction['staff_type'];
                        self::DbQuery(
                            "UPDATE staff SET is_excellent = 1
                             WHERE player_id = {$winnerId}
                             AND staff_type = '{$slotType}'"
                        );
                    } else {
                        // Help Wanted: from box
                        $slotType = $this->findAvailableSlot($auction['staff_type'], $winnerId)
                                    ?? $auction['staff_type'];
                        self::DbQuery(
                            "UPDATE staff SET is_excellent = 1
                             WHERE player_id = {$winnerId}
                             AND staff_type = '{$slotType}'"
                        );
                        self::DbQuery(
                            "UPDATE staff_box SET available = 0
                             WHERE staff_type = '{$slotType}'"
                        );
                    }

                    self::incStat(1, 'staffBidsWon', $winnerId);

                    self::notifyAllPlayers('auctionResolved',
                        clienttranslate('${player_name} wins the auction for ${staff_type} with a bid of ${amount} Duckats.'),
                        array(
                            'player_id'   => $winnerId,
                            'player_name' => self::getPlayerNameById($winnerId),
                            'staff_type'  => $auction['staff_type'],
                            'amount'      => $winAmount,
                        )
                    );
                } else {
                    self::notifyAllPlayers('auctionResolved',
                        clienttranslate('No bids were placed. The staff tile returns to the Staff Box.'),
                        array()
                    );
                }

                self::DbQuery(
                    "UPDATE auction SET status = 'resolved' WHERE auction_id = {$auctionId}"
                );
            }

            self::setGameStateValue('auctionId', 0);
        }

        // Check win condition
        $activePlayerId = self::getActivePlayerId();
        if ($this->hasWon($activePlayerId)) {
            $this->bga->playerScore->inc(1, $activePlayerId);
            self::notifyAllPlayers('gameWon',
                clienttranslate('${player_name} has hired all 12 Excellent staff and wins the game!'),
                array(
                    'player_id'   => $activePlayerId,
                    'player_name' => self::getActivePlayerName(),
                )
            );
            $this->gamestate->nextState('toGameEnd');
            return;
        }

        // Advance to next non-vacation player
        $maxSkips = count(self::loadPlayersBasicInfos());
        for ($i = 0; $i < $maxSkips; $i++) {
            $this->activeNextPlayer();
            $nextId   = self::getActivePlayerId();
            $vacation = (int) self::getUniqueValueFromDB(
                "SELECT player_on_vacation FROM player WHERE player_id = {$nextId}"
            );
            if ($vacation) {
                self::DbQuery(
                    "UPDATE player SET player_on_vacation = 0 WHERE player_id = {$nextId}"
                );
                self::notifyAllPlayers('playerSkipped',
                    clienttranslate('${player_name} is on vacation and loses their turn.'),
                    array(
                        'player_id'   => $nextId,
                        'player_name' => self::getPlayerNameById($nextId),
                    )
                );
            } else {
                break;
            }
        }

        self::incStat(1, 'totalRounds', 0);

        // Reset per-turn state
        self::setGameStateValue('currentQuestion',   0);
        self::setGameStateValue('isStaffDieResult',  0);
        self::setGameStateValue('staffDieResult',    -1);
        self::setGameStateValue('movementRoll',      0);
        self::setGameStateValue('souperDuckatsUsed', 0);
        self::setGameStateValue('hireType',           0);
        self::setGameStateValue('hireHalfPrice',      0);
        self::setGameStateValue('helpWantedPending',  0);
        self::setGameStateValue('helpWantedValue',    0);
        $this->setCardType('');
        $this->setHelpWantedStaffType('');

        $this->gamestate->nextState('toChooseQuestion');
    }

    // ==================================================================
    // STATE ARGUMENTS
    // ==================================================================

    function argResolveSquare()
    {
        $player_id = self::getActivePlayerId();
        $position  = (int) self::getUniqueValueFromDB(
            "SELECT player_board_position FROM player WHERE player_id = {$player_id}"
        );
        return array(
            'position'    => $position,
            'square_type' => $this->getBoardSquare($position),
        );
    }

    function argHireStaff()
    {
        $active_player_id = self::getActivePlayerId();
        return array(
            'hire_type'              => $this->decodeHireType((int) self::getGameStateValue('hireType')),
            'half_price'             => (bool) self::getGameStateValue('hireHalfPrice'),
            'half_price_type'        => $this->getHalfPriceStaffType($this->getCardType()),
            // Bug #6 — help_wanted first-refusal: show only the rolled staff at face value
            'help_wanted_pending'    => (bool) self::getGameStateValue('helpWantedPending'),
            'help_wanted_staff_type' => $this->getHelpWantedStaffType(),
            // Bug #22 — ship the active player's LIVE Duckat balance so the picker does not
            // fall back to the stale page-load gamedatas snapshot.
            'duckats'                => $this->getPlayerDuckats($active_player_id),
            // Bug #17 — per-player open-slot map (baseType => open count) so the picker
            // gates hireability from the active player's own staff rows, not the global box.
            'staffAvailability'      => $this->getPlayerStaffAvailability($active_player_id),
        );
    }

    /**
     * Bug #17 — Per-player staff availability.
     * Returns a map of baseType => number of slots still OPEN (not yet Excellent)
     * for the given player, computed from that player's own `staff` rows.
     * Single-slot roles yield 0 or 1; multi-slot roles (cook/server) yield 0..slots.
     * Mirrors findAvailableSlot()'s definition of "open" (is_excellent = 0).
     */
    private function getPlayerStaffAvailability($playerId)
    {
        $playerId     = (int) $playerId;
        $availability = array();
        foreach (self::STAFF as $s) {
            $baseType = $s['type'];
            if ($s['slots'] > 1) {
                // Multi-slot: count numbered rows (cook_1..3) still open for this player.
                $open = (int) self::getUniqueValueFromDB(
                    "SELECT COUNT(*) FROM staff
                     WHERE player_id = {$playerId}
                     AND staff_type LIKE '" . addslashes($baseType) . "_%'
                     AND is_excellent = 0"
                );
            } else {
                // Single-slot: open if this player's row for the base type is not Excellent.
                $open = (int) self::getUniqueValueFromDB(
                    "SELECT COUNT(*) FROM staff
                     WHERE player_id = {$playerId}
                     AND staff_type = '" . addslashes($baseType) . "'
                     AND is_excellent = 0"
                );
            }
            $availability[$baseType] = $open;
        }
        return $availability;
    }

    // ==================================================================
    // ZOMBIE TURN
    // ==================================================================

    function zombieTurn($state, $active_player)
    {
        $statename = $state['name'];

        if ($state['type'] === 'activeplayer') {
            switch ($statename) {
                case 'chooseQuestion':
                    $this->chooseLetter('A');
                    break;
                case 'answerQuestion':
                    $this->gamestate->nextState('toRollStaffDie');
                    break;
                case 'rollStaffDie':
                    $this->rollStaffDie();
                    break;
                case 'rollMovement':
                    $this->rollMovement();
                    break;
                case 'souperDuckatUse':
                    // Zombie always skips Souper Duckat use
                    $this->gamestate->nextState('toEndTurn');
                    break;
                case 'restaurantCardRoll':
                    $this->rollForCard();
                    break;
                case 'hireStaff':
                    // Zombie always passes on hire
                    $this->passHire();
                    break;
                default:
                    $this->gamestate->nextState('zombiePass');
                    break;
            }
            return;
        }

        if ($state['type'] === 'multipleactiveplayer') {
            // Zombie always passes on auctions
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new feException("Zombie mode not supported at state: {$statename}");
    }

    // ==================================================================
    // UTILITY METHODS
    // ==================================================================

    private function getPlayerDuckats($player_id)
    {
        return (int) self::getUniqueValueFromDB(
            "SELECT player_duckats FROM player WHERE player_id = {$player_id}"
        );
    }

    private function adjustDuckats($player_id, $amount)
    {
        if ($amount === 0) return;
        if ($amount > 0) {
            self::DbQuery(
                "UPDATE player SET player_duckats = player_duckats + {$amount}
                 WHERE player_id = {$player_id}"
            );
        } else {
            $abs = abs($amount);
            self::DbQuery(
                "UPDATE player
                 SET player_duckats = GREATEST(0, CAST(player_duckats AS SIGNED) - {$abs})
                 WHERE player_id = {$player_id}"
            );
        }
    }

    private function getExcellentStaffCount($player_id)
    {
        return (int) self::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM staff
             WHERE player_id = {$player_id} AND is_excellent = 1"
        );
    }

    private function hasWon($player_id)
    {
        return $this->getExcellentStaffCount($player_id) >= self::TOTAL_STAFF;
    }

    private function getBoardSquare($position)
    {
        return self::BOARD_SQUARES[$position % self::BOARD_SIZE];
    }

    private function rollStaffDieInternal()
    {
        $roll = bga_rand(0, count(self::STAFF) - 1);
        return array('index' => $roll, 'staff' => self::STAFF[$roll]);
    }

    /**
     * Calculate bonus for rolling the Staff Die.
     * For cook/server, collect for each Excellent copy owned.
     */
    private function calculateStaffDieBonus($player_id, $staffDef)
    {
        $type    = $staffDef['type'];
        $slots   = $staffDef['slots'];
        $halfVal = (int) floor($staffDef['value'] / 2);

        if ($slots > 1) {
            // Count all excellent numbered slots for this base type
            $count = (int) self::getUniqueValueFromDB(
                "SELECT COUNT(*) FROM staff
                 WHERE player_id = {$player_id}
                 AND staff_type LIKE '" . addslashes($type) . "_%'
                 AND is_excellent = 1"
            );
            return $halfVal * $count;
        } else {
            $isExcellent = (int) self::getUniqueValueFromDB(
                "SELECT is_excellent FROM staff
                 WHERE player_id = {$player_id} AND staff_type = '" . addslashes($type) . "'"
            );
            return $isExcellent ? $halfVal : 0;
        }
    }

    /**
     * Find the next available numbered slot for a multi-slot staff type.
     * Returns e.g. 'cook_2' or null if all slots are excellent for this player.
     */
    private function findAvailableSlot($baseType, $playerId)
    {
        // Normalise: strip any numeric suffix so 'cook_3' → 'cook'.
        // The picker sends the already-numbered firstAvailableSlotType; without this
        // normalisation the loop builds 'cook_3_1', 'cook_3_2' etc. (Bug #21).
        $baseType = preg_replace('/_\d+$/', '', $baseType);

        $staffDef = $this->getStaffDefByType($baseType);
        if ($staffDef === null || $staffDef['slots'] === 1) {
            return $baseType; // Single-slot type — return as-is
        }

        for ($i = 1; $i <= $staffDef['slots']; $i++) {
            $slotType    = $baseType . '_' . $i;
            $isExcellent = (int) self::getUniqueValueFromDB(
                "SELECT is_excellent FROM staff
                 WHERE player_id = {$playerId} AND staff_type = '{$slotType}'"
            );
            if (!$isExcellent) {
                return $slotType;
            }
        }
        return null; // All slots filled
    }

    /**
     * Look up a staff definition by base type name (e.g. 'cook', 'chef').
     * Also accepts numbered types ('cook_1') by stripping the suffix.
     */
    private function getStaffDefByType($type)
    {
        // Strip numbered suffix if present (cook_1 → cook)
        $baseType = preg_replace('/_\d+$/', '', $type);
        foreach (self::STAFF as $s) {
            if ($s['type'] === $baseType) {
                return $s;
            }
        }
        return null;
    }

    /**
     * Hire a staff tile from the Staff Box onto a player's board.
     */
    private function hireFromBox($staffType, $playerId, $price)
    {
        // Find the correct numbered slot if multi-slot type
        $slotType = $this->findAvailableSlot($staffType, $playerId) ?? $staffType;

        $this->adjustDuckats($playerId, -$price);

        self::DbQuery(
            "UPDATE staff SET is_excellent = 1
             WHERE player_id = {$playerId} AND staff_type = '{$slotType}'"
        );
        self::DbQuery(
            "UPDATE staff_box SET available = 0 WHERE staff_type = '{$slotType}'"
        );

        self::notifyAllPlayers('staffHired',
            clienttranslate('${player_name} hires ${staff_type} for ${price} Duckats'),
            array(
                'player_id'      => $playerId,
                'player_name'    => self::getPlayerNameById($playerId),
                'staff_type'     => $staffType,
                'slot_type'      => $slotType,
                'price'          => $price,
                'player_duckats' => $this->getPlayerDuckats($playerId),
            )
        );
    }

    private function initiateStaffQuits($player_id)
    {
        $result   = $this->rollStaffDieInternal();
        $staffDef = $result['staff'];
        $type     = $staffDef['type'];

        // Find an excellent slot of this type on the player's board
        $excellentSlot = null;
        if ($staffDef['slots'] > 1) {
            for ($i = 1; $i <= $staffDef['slots']; $i++) {
                $slotType    = $type . '_' . $i;
                $isExcellent = (int) self::getUniqueValueFromDB(
                    "SELECT is_excellent FROM staff
                     WHERE player_id = {$player_id} AND staff_type = '{$slotType}'"
                );
                if ($isExcellent) {
                    $excellentSlot = $slotType;
                    break;
                }
            }
        } else {
            $isExcellent = (int) self::getUniqueValueFromDB(
                "SELECT is_excellent FROM staff
                 WHERE player_id = {$player_id} AND staff_type = '{$type}'"
            );
            if ($isExcellent) $excellentSlot = $type;
        }

        if ($excellentSlot === null) {
            self::notifyAllPlayers('squareLanded',
                clienttranslate('${player_name} lands on Staff Quits! Rolls ${staff_type} — does not have this staff. Turn ends.'),
                array(
                    'player_id'   => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'square_type' => 'staff_quits',
                    'staff_type'  => $type,
                )
            );
            $this->gamestate->nextState('toEndTurn');
            return;
        }

        // Mark the slot as average
        self::DbQuery(
            "UPDATE staff SET is_excellent = 0
             WHERE player_id = {$player_id} AND staff_type = '{$excellentSlot}'"
        );

        self::DbQuery(
            "INSERT INTO auction (staff_type, staff_value, source, original_owner, status)
             VALUES ('{$type}', {$staffDef['value']}, 'staff_quits', {$player_id}, 'active')"
        );
        $auctionId = self::DbGetLastId();
        self::setGameStateValue('auctionId', $auctionId);

        self::notifyAllPlayers('staffQuits',
            clienttranslate('${player_name}\'s ${staff_type} has quit! Bidding opens.'),
            array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'staff_type'  => $type,
                'staff_value' => $staffDef['value'],
                'auction_id'  => $auctionId,
                'source'      => 'staff_quits',   // Bug #15 — JS modal needs this for correct title
            )
        );

        $this->gamestate->nextState('toStaffQuits');
    }

    private function initiateHelpWanted($player_id)
    {
        $result   = $this->rollStaffDieInternal();
        $staffDef = $result['staff'];
        $type     = $staffDef['type'];

        // Bug #27 — availability for the ACTIVE player's own hire is a per-player
        // question, not a global Staff Box question. Physical availability is per
        // player board (each player has their own slot for every role). Use the
        // player's own staff rows via findAvailableSlot (is_excellent = 0 = open).
        // Returns the first open numbered/single slot, or null if the active player
        // already owns every slot of this type.
        $availableSlot = $this->findAvailableSlot($type, $player_id);

        if ($availableSlot === null) {
            // Active player already owns all slots of this role and cannot hire it.
            // Bug #28 / FR-2 — the staff does not vanish: the other player(s) may
            // hire it. Route to the auction (3-4p) or the single-opponent 1.5x
            // offer (2p) instead of ending the turn.
            self::notifyAllPlayers('helpWanted',
                clienttranslate('${player_name} lands on Help Wanted! Rolls ${staff_type} — already fully staffed, so it is offered to the other player(s).'),
                array(
                    'player_id'   => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'staff_type'  => $type,
                    'staff_value' => $staffDef['value'],
                    'help_wanted' => true,
                )
            );
            $this->routeHelpWantedToOthers($type, (int) $staffDef['value']);
            return;
        }

        // Bug #6 fix — active player gets first refusal at face value via hireStaff.
        // Store rolled staff type/value; passHire creates the auction if they decline.
        $hireTypeCode = ($staffDef['location'] === 'kitchen') ? 0 : 1;
        self::setGameStateValue('hireType',          $hireTypeCode);
        self::setGameStateValue('hireHalfPrice',     0);
        self::setGameStateValue('helpWantedPending', 1);
        self::setGameStateValue('helpWantedValue',   $staffDef['value']);
        $this->setHelpWantedStaffType($type);

        self::notifyAllPlayers('helpWanted',
            clienttranslate('${player_name} lands on Help Wanted! ${staff_type} is available — first right to hire.'),
            array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'staff_type'  => $type,
                'staff_value' => $staffDef['value'],
                'help_wanted' => true,
            )
        );

        // Active player gets first-refusal via hireStaff (picker filtered to this staff only)
        $this->gamestate->nextState('toHireStaff');
    }

    /**
     * Bug #28 / FR-2 — route a Help Wanted staff to the OTHER player(s) after the
     * active player declines or cannot hire it. Called from initiateHelpWanted
     * (cannot-hire branch) and passHire (voluntary-pass branch), so both paths
     * behave identically.
     *
     *   3-4 players: standard rulebook auction. Opening bid = face value (the first
     *                interested player offers the value of the staff), enforced by
     *                seeding current_high_bid = faceValue - 1 so the first valid bid
     *                must be >= faceValue. Routes to state 12 (helpWantedBid).
     *
     *   2 players:   FR-2 single-opponent offer. No bidding. The one opponent is
     *                offered the staff at ceil(1.5 x faceValue), take-it-or-leave-it,
     *                shown via the standard staff picker with a marked-up price.
     *                Routes to state 14 (helpWantedOffer). If the opponent cannot
     *                afford the markup or has no open slot, the turn ends here.
     */
    private function routeHelpWantedToOthers($staffType, $staffValue)
    {
        // Clear the first-refusal context; we are past the active player's option.
        self::setGameStateValue('helpWantedPending', 0);
        self::setGameStateValue('hireHalfPrice',     0);
        self::setGameStateValue('hireType',          0);

        $numPlayers = count(self::loadPlayersBasicInfos());

        if ($numPlayers <= 2) {
            // ---- FR-2: 2-player single-opponent offer at ceil(1.5x) ----
            $activePlayerId = (int) self::getActivePlayerId();

            // Identify the single opponent (the one non-active player).
            $opponentId = null;
            foreach (self::loadPlayersBasicInfos() as $pid => $pinfo) {
                if ((int) $pid !== $activePlayerId) {
                    $opponentId = (int) $pid;
                    break;
                }
            }

            $offerPrice = (int) ceil($staffValue * 1.5);

            // Valid taker requires: opponent exists, has an OPEN slot for this role
            // on their own board, and can afford the marked-up price.
            $opponentSlot = ($opponentId !== null)
                ? $this->findAvailableSlot($staffType, $opponentId)
                : null;
            $opponentDuckats = ($opponentId !== null)
                ? $this->getPlayerDuckats($opponentId)
                : 0;

            if ($opponentId === null || $opponentSlot === null || $opponentDuckats < $offerPrice) {
                self::notifyAllPlayers('helpWantedNoTaker',
                    clienttranslate('No one hires ${staff_type}. It returns to the Staff Box and the turn ends.'),
                    array(
                        'staff_type' => $staffType,
                    )
                );
                self::setGameStateValue('helpWantedOffer', 0);
                self::setGameStateValue('helpWantedValue', 0);
                $this->setHelpWantedStaffType('');
                $this->gamestate->nextState('toEndTurn');
                return;
            }

            // Store offer context. helpWantedValue holds the MARKED-UP price the
            // opponent pays; helpWantedOffer flags markup mode for the picker.
            self::setGameStateValue('helpWantedOffer', 1);
            self::setGameStateValue('helpWantedValue', $offerPrice);
            $this->setHelpWantedStaffType($staffType);

            self::notifyAllPlayers('helpWantedOfferMade',
                clienttranslate('${staff_type} is offered to ${opponent_name} for ${amount} Duckats (1.5x value).'),
                array(
                    'staff_type'    => $staffType,
                    'opponent_name' => self::getPlayerNameById($opponentId),
                    'amount'        => $offerPrice,
                    'opponent_id'   => $opponentId,
                )
            );

            $this->gamestate->nextState('toHelpWantedOffer');
            return;
        }

        // ---- 3-4 players: standard rulebook auction, opening bid = face value ----
        self::setGameStateValue('helpWantedOffer', 0);
        self::setGameStateValue('helpWantedValue', 0);
        $this->setHelpWantedStaffType('');

        $safeType = addslashes($staffType);
        // Seed current_high_bid = faceValue - 1 so the first valid bid must be >= faceValue
        // (rulebook: the first interested player offers the value of the staff). Bug #28 Q2.
        $floor = max(0, (int) $staffValue - 1);
        self::DbQuery(
            "INSERT INTO auction (staff_type, staff_value, source, current_high_bid, status)
             VALUES ('{$safeType}', " . (int) $staffValue . ", 'help_wanted', {$floor}, 'active')"
        );
        $auctionId = self::DbGetLastId();
        self::setGameStateValue('auctionId', $auctionId);

        self::notifyAllPlayers('helpWantedAuction',
            clienttranslate('${staff_type} goes to auction — opening bid ${amount} Duckats!'),
            array(
                'staff_type'  => $staffType,
                'staff_value' => (int) $staffValue,
                'amount'      => (int) $staffValue,
                'auction_id'  => $auctionId,
                'source'      => 'help_wanted',
            )
        );

        $this->gamestate->nextState('toHelpWanted');
    }

    private function drawNextQuestion()
    {
        $question = self::getObjectFromDB(
            "SELECT * FROM question WHERE used = 0 ORDER BY card_order ASC LIMIT 1"
        );
        if ($question === null) {
            // Reshuffle: reset all to unused with new random order
            $ids   = self::getObjectListFromDB("SELECT question_id FROM question", true);
            $order = range(1, count($ids));
            shuffle($order);
            foreach (array_values($ids) as $i => $qid) {
                self::DbQuery(
                    "UPDATE question SET used = 0, card_order = " . (int)$order[$i] .
                    " WHERE question_id = " . (int)$qid
                );
            }
            $question = self::getObjectFromDB(
                "SELECT * FROM question WHERE used = 0 ORDER BY card_order ASC LIMIT 1"
            );
        }
        if ($question !== null) {
            self::DbQuery(
                "UPDATE question SET used = 1 WHERE question_id = " . (int)$question['question_id']
            );
        }
        return $question;
    }

    /**
     * Decode integer hireType game state to string.
     * 0 = kitchen, 1 = dining_room, 2 = either
     */
    private function decodeHireType($code)
    {
        $map = array(0 => 'kitchen', 1 => 'dining_room', 2 => 'either');
        return $map[$code] ?? 'kitchen';
    }

    /**
     * Return the specific staff type required for half-price card hires.
     */
    private function getHalfPriceStaffType(string $cardType)
    {
        $map = array(
            'chef_cook_bonus' => 'cook',
            'maitre_d_bonus'  => 'server',
        );
        return $map[$cardType] ?? null;
    }

    /**
     * Read the pending restaurant card type from the game_state_text table.
     * Returns empty string when no card roll is pending.
     */
    private function getCardType(): string
    {
        $val = self::getUniqueValueFromDB(
            "SELECT state_value FROM game_state_text WHERE state_key = 'cardType'"
        );
        return $val ?? '';
    }

    /**
     * Write the pending restaurant card type to the game_state_text table.
     * Pass empty string to clear.
     */
    private function setCardType(string $cardType): void
    {
        $safe = addslashes($cardType);
        self::DbQuery(
            "INSERT INTO game_state_text (state_key, state_value)
             VALUES ('cardType', '{$safe}')
             ON DUPLICATE KEY UPDATE state_value = '{$safe}'"
        );
    }

    /**
     * Bug #6 — Read the help_wanted rolled staff base type (e.g. 'sous_chef').
     */
    private function getHelpWantedStaffType(): string
    {
        $val = self::getUniqueValueFromDB(
            "SELECT state_value FROM game_state_text WHERE state_key = 'helpWantedStaffType'"
        );
        return $val ?? '';
    }

    /**
     * Bug #6 — Write the help_wanted rolled staff base type.
     * Pass empty string to clear.
     */
    private function setHelpWantedStaffType(string $type): void
    {
        $safe = addslashes($type);
        self::DbQuery(
            "INSERT INTO game_state_text (state_key, state_value)
             VALUES ('helpWantedStaffType', '{$safe}')
             ON DUPLICATE KEY UPDATE state_value = '{$safe}'"
        );
    }

    // ==================================================================
    // UPGRADE
    // ==================================================================

    function upgradeTableDb($from_version)
    {
        // Future schema migrations go here
    }
}
