<?php
/**
 * ducksouptherestaurantgamegame.php
 *
 * Duck Soup — The Restaurant Game
 * BGA implementation: Phase 1
 *
 * Implements:
 *   - setupNewGame()       Full player + staff + question initialisation
 *   - getAllDatas()         All data visible to the current player
 *   - getGameProgression() Based on most-advanced player's excellent staff count
 *   - State actions:       stResolveSquare, stEndTurn, stStaffQuitsBid, stHelpWantedBid
 *   - Player actions:      chooseLetter, submitAnswer, rollStaffDie, rollMovement,
 *                          playSouperDuckat, resolveRestaurantCard, returnStaffForPayment,
 *                          hireStaff, placeBid, passBid
 *   - zombieTurn()
 */

require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');
require_once('questions_seed.php');

class duckSoupTheRestaurantGame extends Table
{
    // ------------------------------------------------------------------
    // Staff definitions — mirrors the physical Staff Board.
    // Values per the game rules (back of the Restaurant Sign card).
    // ------------------------------------------------------------------
    const STAFF = array(
        // Kitchen
        array('type' => 'chef',        'location' => 'kitchen',     'value' => 60),
        array('type' => 'sous_chef',   'location' => 'kitchen',     'value' => 50),
        array('type' => 'first_cook',  'location' => 'kitchen',     'value' => 40),
        array('type' => 'cook_1',      'location' => 'kitchen',     'value' => 30),
        array('type' => 'cook_2',      'location' => 'kitchen',     'value' => 30),
        array('type' => 'cook_3',      'location' => 'kitchen',     'value' => 30),
        // Dining room
        array('type' => 'maitre_d',    'location' => 'dining_room', 'value' => 60),
        array('type' => 'sommelier',   'location' => 'dining_room', 'value' => 50),
        array('type' => 'captain',     'location' => 'dining_room', 'value' => 40),
        array('type' => 'server_1',    'location' => 'dining_room', 'value' => 30),
        array('type' => 'server_2',    'location' => 'dining_room', 'value' => 30),
        array('type' => 'server_3',    'location' => 'dining_room', 'value' => 30),
    );

    // Board squares in clockwise order from position 0 (Duck Soup square).
    // 36 squares total. Positions match board layout.
    const BOARD_SQUARES = array(
        0  => 'duck_soup',
        1  => 'restaurant',
        2  => 'staff_quits',
        3  => 'bistro_help_wanted',
        4  => 'restaurant',
        5  => 'staff_quits',
        6  => 'hire_dining_room',
        7  => 'restaurant',
        8  => 'hire_kitchen_or_dining',
        9  => 'staff_quits',
        10 => 'bistro_help_wanted',
        11 => 'restaurant',
        12 => 'staff_quits',
        13 => 'hire_kitchen',
        14 => 'restaurant',
        15 => 'renos_repairs',
        16 => 'restaurant',
        17 => 'staff_quits',
        18 => 'bistro_help_wanted',
        19 => 'restaurant',
        20 => 'business_great',
        21 => 'restaurant',
        22 => 'hire_kitchen_or_dining',
        23 => 'staff_quits',
        24 => 'bistro_help_wanted',
        25 => 'restaurant',
        26 => 'staff_quits',
        27 => 'hire_dining_room',
        28 => 'restaurant',
        29 => 'hire_kitchen',
        30 => 'restaurant',
        31 => 'staff_quits',
        32 => 'bistro_help_wanted',
        33 => 'restaurant',
        34 => 'vacation',
        35 => 'renos_repairs',
    );

    const BOARD_SIZE        = 36;
    const STARTING_DUCKATS  = 300;
    const SOUPER_DUCKAT_BUY = 50;
    const SOUPER_DUCKAT_CASH= 25;
    const TOTAL_STAFF       = 12;

    function __construct()
    {
        parent::__construct();
        self::initGameStateLabels(array(
            'currentQuestion'   => 10, // question_id currently being answered
            'currentLetter'     => 11, // 0=A,1=B,2=C,3=D chosen this turn
            'isStaffDieResult'  => 12, // 1 = letter resolved to ROLL STAFF DIE
            'staffDieResult'    => 13, // index into STAFF array (0-11) or -1
            'movementRoll'      => 14, // total dice roll this turn
            'souperDuckatsUsed' => 15, // how many played this turn
            'auctionId'         => 16, // current auction row id (0 = none)
            'turnNumber'        => 17, // incremented each full round
        ));
    }

    protected function getGameName()
    {
        return 'ducksouptherestaurantgame';
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
                player_duckats        = " . self::STARTING_DUCKATS . ",
                player_souper_duckats = 3,
                player_board_position = 0,
                player_restaurant_name = '',
                player_on_vacation    = 0
                WHERE player_id = {$player_id}");
        }

        // --- Populate staff_box (12 tile types available at game start) ---
        $staffValues = array();
        foreach (self::STAFF as $s) {
            $staffValues[] = "('{$s['type']}','{$s['location']}',{$s['value']},1)";
        }
        self::DbQuery(
            'INSERT INTO staff_box (staff_type, staff_location, staff_value, available) VALUES '
            . implode(',', $staffValues)
        );

        // --- Create staff rows for each player (all Average to start) ---
        $playerStaff = array();
        foreach ($players as $player_id => $player) {
            foreach (self::STAFF as $s) {
                $playerStaff[] = "({$player_id},'{$s['type']}','{$s['location']}',0,{$s['value']})";
            }
        }
        self::DbQuery(
            'INSERT INTO staff (player_id, staff_type, staff_location, is_excellent, staff_value) VALUES '
            . implode(',', $playerStaff)
        );

        // --- Each player hires 3 Excellent staff before play begins ---
        $this->setupInitialStaffHires($players);

        // --- Seed the question deck ---
        duckSoupTheRestaurantGameQuestions::seed($this);

        // --- Init global state values ---
        self::setGameStateInitialValue('currentQuestion',   0);
        self::setGameStateInitialValue('currentLetter',     0);
        self::setGameStateInitialValue('isStaffDieResult',  0);
        self::setGameStateInitialValue('staffDieResult',   -1);
        self::setGameStateInitialValue('movementRoll',      0);
        self::setGameStateInitialValue('souperDuckatsUsed', 0);
        self::setGameStateInitialValue('auctionId',         0);
        self::setGameStateInitialValue('turnNumber',        1);

        // --- Init statistics ---
        self::initStat('table', 'totalRounds',  0);
        self::initStat('table', 'bankDuckats',  0);
        foreach ($players as $player_id => $player) {
            self::initStat('player', 'duckats',        $player_id);
            self::initStat('player', 'souperDuckats',  $player_id);
            self::initStat('player', 'excellentStaff', $player_id);
            self::initStat('player', 'normalStaff',    $player_id);
            self::initStat('player', 'staffBids',      $player_id);
            self::initStat('player', 'staffBidsWon',   $player_id);
        }

        // --- Determine first player (highest die roll, ties re-roll) ---
        $this->activeNextPlayer();
    }

    /**
     * Before regular play, each player hires 3 Excellent staff by rolling
     * the Staff Die in clockwise order. Each player must take what they roll
     * (unless already have it and it's not cook/server).
     */
    private function setupInitialStaffHires($players)
    {
        $playerIds = array_keys($players);

        foreach ($playerIds as $player_id) {
            $hired = 0;
            $attempts = 0;

            while ($hired < 3 && $attempts < 50) {
                $attempts++;
                $roll      = bga_rand(0, 11); // 12-sided: index into STAFF
                $staffType = self::STAFF[$roll];

                // Check if already have it (unless cook or server)
                $alreadyExcellent = self::getUniqueValueFromDB(
                    "SELECT is_excellent FROM staff
                     WHERE player_id = {$player_id}
                     AND staff_type = '{$staffType['type']}'"
                );

                $isMultiple = in_array($staffType['type'], array('cook_1','cook_2','cook_3','server_1','server_2','server_3'));

                if ($alreadyExcellent == 1 && !$isMultiple) {
                    continue; // re-roll per rules
                }

                // Check availability in box
                $available = self::getUniqueValueFromDB(
                    "SELECT available FROM staff_box WHERE staff_type = '{$staffType['type']}'"
                );
                if (!$available) {
                    continue;
                }

                // Hire: pay the bank, mark excellent on player board, mark unavailable in box
                $cost = $staffType['value'];
                self::DbQuery(
                    "UPDATE player SET player_duckats = player_duckats - {$cost}
                     WHERE player_id = {$player_id}"
                );
                self::DbQuery(
                    "UPDATE staff SET is_excellent = 1
                     WHERE player_id = {$player_id} AND staff_type = '{$staffType['type']}'"
                );
                self::DbQuery(
                    "UPDATE staff_box SET available = 0 WHERE staff_type = '{$staffType['type']}'"
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
        $result             = array();
        $current_player_id  = self::getCurrentPlayerId();

        // Basic player info + Duck Soup fields
        $result['players'] = self::getCollectionFromDB(
            'SELECT player_id id, player_score score, player_color color,
                    player_name name, player_duckats duckats,
                    player_souper_duckats souper_duckats,
                    player_board_position board_position,
                    player_restaurant_name restaurant_name,
                    player_on_vacation on_vacation
             FROM player'
        );

        // Staff boards — all players (excellent status is public)
        $result['staff'] = self::getCollectionFromDB(
            'SELECT staff_id, player_id, staff_type, staff_location,
                    is_excellent, staff_value
             FROM staff
             ORDER BY player_id, staff_location, staff_value DESC',
            'staff_id'
        );

        // Staff box availability
        $result['staffBox'] = self::getCollectionFromDB(
            'SELECT staff_type, staff_location, staff_value, available
             FROM staff_box',
            'staff_type'
        );

        // Current question (only reveal to active player — others see that a question is active)
        $questionId = (int) self::getGameStateValue('currentQuestion');
        if ($questionId > 0) {
            $activePlayerId = self::getActivePlayerId();
            if ($current_player_id == $activePlayerId) {
                // Active player sees the full question
                $result['currentQuestion'] = self::getObjectFromDB(
                    "SELECT question_id, duckats_value, category, question_text,
                             answer_a, answer_b, answer_c, answer_d
                      FROM question WHERE question_id = {$questionId}"
                );
            } else {
                // Other players only know a question is in progress
                $result['currentQuestion'] = array('question_id' => $questionId, 'hidden' => true);
            }
        }

        // Active auction (if any)
        $auctionId = (int) self::getGameStateValue('auctionId');
        if ($auctionId > 0) {
            $result['auction'] = self::getObjectFromDB(
                "SELECT * FROM auction WHERE auction_id = {$auctionId}"
            );
        }

        // Global state flags useful to the UI
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
        // Progress = best player's excellent staff count / 12 * 100
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
        // Amount may be negative (payment) or positive (collection)
        self::DbQuery(
            "UPDATE player SET player_duckats = player_duckats + ({$amount})
             WHERE player_id = {$player_id}"
        );
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

    /**
     * Roll the 12-sided Staff Die and return the staff definition array.
     */
    private function rollStaffDieInternal()
    {
        $roll = bga_rand(0, 11);
        return array('index' => $roll, 'staff' => self::STAFF[$roll]);
    }

    /**
     * Transfer an Excellent staff tile from one player's board to another's.
     * Handles all DB updates.
     */
    private function transferStaff($staffType, $fromPlayerId, $toPlayerId, $price)
    {
        // Deduct from buyer, nothing goes to bank per rules (bid → bank)
        $this->adjustDuckats($toPlayerId, -$price);

        // Move tile
        self::DbQuery(
            "UPDATE staff SET is_excellent = 0
             WHERE player_id = {$fromPlayerId} AND staff_type = '{$staffType}'"
        );
        self::DbQuery(
            "UPDATE staff SET is_excellent = 1
             WHERE player_id = {$toPlayerId} AND staff_type = '{$staffType}'"
        );

        self::notifyAllPlayers('staffTransferred', clienttranslate('${player_name} hires ${staff_type} from another player for ${price} Duckats'), array(
            'player_id'   => $toPlayerId,
            'player_name' => self::getActivePlayerName(),
            'staff_type'  => $staffType,
            'price'       => $price,
        ));
    }

    /**
     * Hire a staff tile from the Staff Box onto a player's board.
     */
    private function hireFromBox($staffType, $playerId, $price)
    {
        $this->adjustDuckats($playerId, -$price);

        self::DbQuery(
            "UPDATE staff SET is_excellent = 1
             WHERE player_id = {$playerId} AND staff_type = '{$staffType}'"
        );
        self::DbQuery(
            "UPDATE staff_box SET available = 0 WHERE staff_type = '{$staffType}'"
        );

        self::notifyAllPlayers('staffHired', clienttranslate('${player_name} hires ${staff_type} for ${price} Duckats'), array(
            'player_id'   => $playerId,
            'player_name' => self::getPlayerNameById($playerId),
            'staff_type'  => $staffType,
            'price'       => $price,
            'duckats'     => $this->getPlayerDuckats($playerId),
        ));
    }

    // ==================================================================
    // PLAYER ACTIONS
    // ==================================================================

    /**
     * Action: chooseLetter
     * Active player selects A, B, C, or D before rolling dice.
     * Server draws the top question card and resolves what the letter maps to.
     * 25% chance it maps to ROLL STAFF DIE! per the physical card distribution.
     */
    function chooseLetter($letter)
    {
        self::checkAction('chooseLetter');
        $player_id = self::getActivePlayerId();

        if (!in_array($letter, array('A', 'B', 'C', 'D'))) {
            throw new BgaUserException(self::_('Invalid letter choice.'));
        }

        // Draw the next question
        $question = duckSoupTheRestaurantGameQuestions::drawNext($this);
        if ($question === null) {
            throw new BgaVisibleSystemException('Question deck is empty after reshuffle — check seeder.');
        }

        self::setGameStateValue('currentQuestion', (int) $question['question_id']);
        self::setGameStateValue('currentLetter', array_search($letter, array('A','B','C','D')));

        // Determine if the chosen letter maps to a question or ROLL STAFF DIE!
        // In the physical game, one of the four letters maps to ROLL STAFF DIE!
        // We simulate this: on each draw, randomly assign ROLL STAFF DIE! to one letter.
        $staffDieLetter = array('A','B','C','D')[bga_rand(0, 3)];
        $isStaffDie     = ($letter === $staffDieLetter) ? 1 : 0;

        self::setGameStateValue('isStaffDieResult', $isStaffDie);

        // Reveal result to all players
        if ($isStaffDie) {
            self::notifyAllPlayers('letterChosen', clienttranslate('${player_name} chose ${letter} — Roll Staff Die!'), array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'letter'      => $letter,
                'is_staff_die'=> true,
            ));
            $this->gamestate->nextState('toRollStaffDie');
        } else {
            // Reveal question to active player only
            self::notifyPlayer($player_id, 'questionRevealed', '', array(
                'question' => $question,
            ));
            self::notifyAllPlayers('letterChosen', clienttranslate('${player_name} chose ${letter} — a question has been drawn'), array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'letter'      => $letter,
                'is_staff_die'=> false,
                'duckats_value' => (int) $question['duckats_value'],
            ));
            $this->gamestate->nextState('toAnswer');
        }
    }

    /**
     * Action: submitAnswer
     * Active player submits their answer to the trivia question.
     */
    function submitAnswer($answer)
    {
        self::checkAction('submitAnswer');
        $player_id  = self::getActivePlayerId();

        if (!in_array($answer, array('A', 'B', 'C', 'D'))) {
            throw new BgaUserException(self::_('Invalid answer.'));
        }

        $questionId = (int) self::getGameStateValue('currentQuestion');
        $question   = self::getObjectFromDB(
            "SELECT * FROM question WHERE question_id = {$questionId}"
        );

        if ($question === null) {
            throw new BgaVisibleSystemException('Could not load question for answer check.');
        }

        $correct       = ($answer === $question['correct_answer']);
        $duckatReward  = $correct ? (int) $question['duckats_value'] : 0;

        if ($correct) {
            $this->adjustDuckats($player_id, $duckatReward);
        }

        self::notifyAllPlayers('answerResult', $correct
            ? clienttranslate('${player_name} answers correctly and collects ${duckats} Duckats!')
            : clienttranslate('${player_name} answers incorrectly. The correct answer was ${correct_answer}.'),
            array(
                'player_id'      => $player_id,
                'player_name'    => self::getActivePlayerName(),
                'answer'         => $answer,
                'correct'        => $correct,
                'correct_answer' => $question['correct_answer'],
                'answer_text'    => $question['answer_text'],
                'duckats'        => $duckatReward,
                'player_duckats' => $this->getPlayerDuckats($player_id),
            )
        );

        $this->gamestate->nextState('toRollStaffDie');
    }

    /**
     * Action: rollStaffDie
     * Roll the 12-sided Staff Die.
     * If the player has the rolled Excellent staff, collect half its value.
     */
    function rollStaffDie()
    {
        self::checkAction('rollStaffDie');
        $player_id = self::getActivePlayerId();

        $result    = $this->rollStaffDieInternal();
        $staffDef  = $result['staff'];
        $index     = $result['index'];

        self::setGameStateValue('staffDieResult', $index);

        // Check if player has this Excellent staff
        $isExcellent = (int) self::getUniqueValueFromDB(
            "SELECT is_excellent FROM staff
             WHERE player_id = {$player_id} AND staff_type = '{$staffDef['type']}'"
        );

        $bonus = 0;
        if ($isExcellent) {
            // Cooks and servers: collect for EACH one they have
            if (in_array($staffDef['type'], array('cook_1','cook_2','cook_3','server_1','server_2','server_3'))) {
                $baseType    = strpos($staffDef['type'], 'cook') !== false ? 'cook' : 'server';
                $countOwned  = (int) self::getUniqueValueFromDB(
                    "SELECT COUNT(*) FROM staff
                     WHERE player_id = {$player_id}
                     AND is_excellent = 1
                     AND staff_type LIKE '{$baseType}%'"
                );
                $bonus = (int) floor($staffDef['value'] / 2) * $countOwned;
            } else {
                $bonus = (int) floor($staffDef['value'] / 2);
            }

            if ($bonus > 0) {
                $this->adjustDuckats($player_id, $bonus);
            }
        }

        self::notifyAllPlayers('staffDieRolled', $isExcellent && $bonus > 0
            ? clienttranslate('${player_name} rolled ${staff_type} and collects ${bonus} Duckats bonus!')
            : clienttranslate('${player_name} rolled ${staff_type} — no bonus.'),
            array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'staff_type'  => $staffDef['type'],
                'is_excellent'=> $isExcellent,
                'bonus'       => $bonus,
                'player_duckats' => $this->getPlayerDuckats($player_id),
            )
        );

        $this->gamestate->nextState('toRollMovement');
    }

    /**
     * Action: rollMovement
     * Roll 2d6 for movement (Souper Duckats already applied via playSouperDuckat).
     */
    function rollMovement()
    {
        self::checkAction('rollMovement');
        $player_id = self::getActivePlayerId();

        $die1 = bga_rand(1, 6);
        $die2 = bga_rand(1, 6);
        $roll = $die1 + $die2 + (int) self::getGameStateValue('souperDuckatsUsed');

        self::setGameStateValue('movementRoll', $roll);

        // Move pawn
        $currentPos = (int) self::getUniqueValueFromDB(
            "SELECT player_board_position FROM player WHERE player_id = {$player_id}"
        );
        $newPos = ($currentPos + $roll) % self::BOARD_SIZE;
        self::DbQuery(
            "UPDATE player SET player_board_position = {$newPos} WHERE player_id = {$player_id}"
        );

        self::notifyAllPlayers('pawnMoved', clienttranslate('${player_name} rolls ${die1} + ${die2} and moves to square ${position}'), array(
            'player_id'   => $player_id,
            'player_name' => self::getActivePlayerName(),
            'die1'        => $die1,
            'die2'        => $die2,
            'total'       => $roll,
            'position'    => $newPos,
            'square_type' => $this->getBoardSquare($newPos),
        ));

        $this->gamestate->nextState('toResolveSquare');
    }

    /**
     * Action: playSouperDuckat
     * Play one or more Souper Duckats before rolling movement for extra squares.
     */
    function playSouperDuckat($count)
    {
        self::checkAction('rollMovement'); // allowed in rollMovement state
        $player_id = self::getActivePlayerId();
        $count     = max(1, (int) $count);

        $owned = (int) self::getUniqueValueFromDB(
            "SELECT player_souper_duckats FROM player WHERE player_id = {$player_id}"
        );

        if ($count > $owned) {
            throw new BgaUserException(self::_('You do not have enough Souper Duckats.'));
        }

        self::DbQuery(
            "UPDATE player SET player_souper_duckats = player_souper_duckats - {$count}
             WHERE player_id = {$player_id}"
        );

        $current = (int) self::getGameStateValue('souperDuckatsUsed');
        self::setGameStateValue('souperDuckatsUsed', $current + $count);

        self::notifyAllPlayers('souperDuckatPlayed', clienttranslate('${player_name} plays ${count} Souper Duckat(s) for extra movement'), array(
            'player_id'            => $player_id,
            'player_name'          => self::getActivePlayerName(),
            'count'                => $count,
            'souper_duckats_left'  => $owned - $count,
        ));
        // No state transition — player still in rollMovement state
    }

    /**
     * Action: hireStaff
     * Used on KITCHEN, DINING ROOM, HIRE K/DR squares, and Help Wanted first offer.
     * Player chooses which available Excellent staff to hire.
     */
    function hireStaff($staffType)
    {
        self::checkAction('hireStaff');
        $player_id = self::getActivePlayerId();

        // Validate staff type exists and is available
        $staffDef = null;
        foreach (self::STAFF as $s) {
            if ($s['type'] === $staffType) {
                $staffDef = $s;
                break;
            }
        }
        if ($staffDef === null) {
            throw new BgaUserException(self::_('Invalid staff type.'));
        }

        $available = (int) self::getUniqueValueFromDB(
            "SELECT available FROM staff_box WHERE staff_type = '{$staffType}'"
        );
        if (!$available) {
            throw new BgaUserException(self::_('That staff member is not available.'));
        }

        $duckats = $this->getPlayerDuckats($player_id);
        if ($duckats < $staffDef['value']) {
            throw new BgaUserException(self::_('Not enough Duckats to hire this staff member.'));
        }

        $this->hireFromBox($staffType, $player_id, $staffDef['value']);
        $this->gamestate->nextState('toEndTurn');
    }

    /**
     * Action: resolveRestaurantCard
     * Active player resolves a drawn Restaurant card (pay/collect/etc.).
     */
    function resolveRestaurantCard()
    {
        self::checkAction('resolveRestaurantCard');
        $player_id = self::getActivePlayerId();

        // Draw the top restaurant card
        $card = self::getObjectFromDB(
            'SELECT * FROM restaurant_card ORDER BY card_order ASC LIMIT 1'
        );

        if ($card === null) {
            // No cards seeded yet (Phase 2 task) — pass through
            self::notifyAllPlayers('restaurantCard', clienttranslate('${player_name} draws a Restaurant card'), array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'card'        => null,
            ));
            $this->gamestate->nextState('toEndTurn');
            return;
        }

        $effect = (int) $card['effect_value'];

        if ($effect > 0) {
            $this->adjustDuckats($player_id, $effect);
        } elseif ($effect < 0) {
            $owes    = abs($effect);
            $current = $this->getPlayerDuckats($player_id);
            if ($current >= $owes) {
                $this->adjustDuckats($player_id, $effect);
            } else {
                // Player cannot pay — they will use returnStaffForPayment
                // Store the debt in a state variable for next action
                // (Phase 2 will implement partial payment flow)
                self::notifyPlayer($player_id, 'paymentRequired', clienttranslate('You must pay ${amount} Duckats — you may return Excellent staff to cover the cost'), array(
                    'amount' => $owes,
                ));
                return; // Stay in resolveRestaurant state
            }
        }

        // Move card to bottom of deck
        self::DbQuery(
            "UPDATE restaurant_card SET card_order = card_order + 1000
             WHERE card_id = {$card['card_id']}"
        );

        self::notifyAllPlayers('restaurantCard', clienttranslate('${player_name} resolves a Restaurant card'), array(
            'player_id'   => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card'        => $card,
            'effect'      => $effect,
            'player_duckats' => $this->getPlayerDuckats($player_id),
        ));

        $this->gamestate->nextState('toEndTurn');
    }

    /**
     * Action: returnStaffForPayment
     * Player returns an Excellent staff tile for half value to cover a debt.
     */
    function returnStaffForPayment($staffType)
    {
        self::checkAction('resolveRestaurantCard');
        $player_id = self::getActivePlayerId();

        $staffDef = null;
        foreach (self::STAFF as $s) {
            if ($s['type'] === $staffType) {
                $staffDef = $s;
                break;
            }
        }
        if ($staffDef === null) {
            throw new BgaUserException(self::_('Invalid staff type.'));
        }

        $isExcellent = (int) self::getUniqueValueFromDB(
            "SELECT is_excellent FROM staff
             WHERE player_id = {$player_id} AND staff_type = '{$staffType}'"
        );
        if (!$isExcellent) {
            throw new BgaUserException(self::_('You can only return Excellent staff.'));
        }

        $refund = (int) floor($staffDef['value'] / 2);

        // Return tile to Staff Box
        self::DbQuery(
            "UPDATE staff SET is_excellent = 0
             WHERE player_id = {$player_id} AND staff_type = '{$staffType}'"
        );
        self::DbQuery(
            "UPDATE staff_box SET available = 1 WHERE staff_type = '{$staffType}'"
        );

        $this->adjustDuckats($player_id, $refund);

        self::notifyAllPlayers('staffReturned', clienttranslate('${player_name} returns ${staff_type} for ${refund} Duckats'), array(
            'player_id'      => $player_id,
            'player_name'    => self::getActivePlayerName(),
            'staff_type'     => $staffType,
            'refund'         => $refund,
            'player_duckats' => $this->getPlayerDuckats($player_id),
        ));
        // Player stays in resolveRestaurant to continue resolving the card
    }

    /**
     * Action: placeBid
     * A player places a bid in the Staff Quits or Help Wanted auction.
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
            throw new BgaUserException(self::_('No active auction.'));
        }
        if ($amount <= (int) $auction['current_high_bid']) {
            throw new BgaUserException(self::_('Your bid must be higher than the current bid.'));
        }
        if ($amount > $this->getPlayerDuckats($player_id)) {
            throw new BgaUserException(self::_('You do not have enough Duckats.'));
        }

        self::DbQuery(
            "UPDATE auction SET
                current_high_bidder = {$player_id},
                current_high_bid    = {$amount}
             WHERE auction_id = {$auctionId}"
        );

        self::incStat(1, 'staffBids', $player_id);

        self::notifyAllPlayers('bidPlaced', clienttranslate('${player_name} bids ${amount} Duckats'), array(
            'player_id'   => $player_id,
            'player_name' => self::getPlayerNameById($player_id),
            'amount'      => $amount,
        ));

        $this->gamestate->setPlayerNonMultiactive($player_id, '');
    }

    /**
     * Action: passBid
     * A player passes on the current auction.
     */
    function passBid()
    {
        self::checkAction('passBid');
        $player_id = self::getCurrentPlayerId();

        self::notifyAllPlayers('bidPassed', clienttranslate('${player_name} passes'), array(
            'player_id'   => $player_id,
            'player_name' => self::getPlayerNameById($player_id),
        ));

        $this->gamestate->setPlayerNonMultiactive($player_id, '');
    }

    // ==================================================================
    // GAME STATE ACTIONS (server-side, no player input)
    // ==================================================================

    /**
     * stResolveSquare
     * Reads the square the active player landed on and routes accordingly.
     */
    function stResolveSquare()
    {
        $player_id = self::getActivePlayerId();
        $position  = (int) self::getUniqueValueFromDB(
            "SELECT player_board_position FROM player WHERE player_id = {$player_id}"
        );
        $squareType = $this->getBoardSquare($position);

        // Reset per-turn tracking
        self::setGameStateValue('souperDuckatsUsed', 0);

        switch ($squareType) {

            case 'duck_soup':
                // Collect 1 Souper Duckat and re-roll
                self::DbQuery(
                    "UPDATE player SET player_souper_duckats = player_souper_duckats + 1
                     WHERE player_id = {$player_id}"
                );
                self::notifyAllPlayers('squareLanded', clienttranslate('${player_name} lands on Duck Soup! Collect a Souper Duckat and roll again.'), array(
                    'player_id'   => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'square_type' => $squareType,
                ));
                $this->gamestate->nextState('toRollMovement');
                break;

            case 'business_great':
                // Roll dice, collect 5× the roll
                $roll   = bga_rand(2, 12);
                $reward = $roll * 5;
                $this->adjustDuckats($player_id, $reward);
                self::notifyAllPlayers('squareLanded', clienttranslate('${player_name} lands on Business Is Great! Rolls ${roll} and collects ${reward} Duckats.'), array(
                    'player_id'   => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'square_type' => $squareType,
                    'roll'        => $roll,
                    'reward'      => $reward,
                    'player_duckats' => $this->getPlayerDuckats($player_id),
                ));
                $this->gamestate->nextState('toEndTurn');
                break;

            case 'renos_repairs':
                // Roll dice, pay 5× the roll
                $roll    = bga_rand(2, 12);
                $penalty = $roll * 5;
                $owes    = min($penalty, $this->getPlayerDuckats($player_id));
                $this->adjustDuckats($player_id, -$owes);
                self::notifyAllPlayers('squareLanded', clienttranslate('${player_name} lands on Renos & Repairs! Rolls ${roll} and pays ${penalty} Duckats.'), array(
                    'player_id'   => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'square_type' => $squareType,
                    'roll'        => $roll,
                    'penalty'     => $owes,
                    'player_duckats' => $this->getPlayerDuckats($player_id),
                ));
                $this->gamestate->nextState('toEndTurn');
                break;

            case 'vacation':
                self::DbQuery(
                    "UPDATE player SET player_on_vacation = 1 WHERE player_id = {$player_id}"
                );
                self::notifyAllPlayers('squareLanded', clienttranslate('${player_name} lands on Vacation! They lose their next turn.'), array(
                    'player_id'   => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'square_type' => $squareType,
                ));
                $this->gamestate->nextState('toEndTurn');
                break;

            case 'restaurant':
                self::notifyAllPlayers('squareLanded', clienttranslate('${player_name} lands on a Restaurant square.'), array(
                    'player_id'   => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'square_type' => $squareType,
                ));
                $this->gamestate->nextState('toRestaurant');
                break;

            case 'staff_quits':
                $this->initiateStaffQuits($player_id);
                break;

            case 'bistro_help_wanted':
                $this->initiateHelpWanted($player_id);
                break;

            case 'hire_kitchen':
            case 'hire_dining_room':
            case 'hire_kitchen_or_dining':
                self::notifyAllPlayers('squareLanded', clienttranslate('${player_name} may hire a staff member.'), array(
                    'player_id'   => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'square_type' => $squareType,
                ));
                // Active player selects via hireStaff() action
                $this->gamestate->nextState('toEndTurn');
                break;

            default:
                $this->gamestate->nextState('toEndTurn');
                break;
        }
    }

    private function initiateStaffQuits($player_id)
    {
        $result  = $this->rollStaffDieInternal();
        $staffDef = $result['staff'];

        // Check if this player has this Excellent staff
        $isExcellent = (int) self::getUniqueValueFromDB(
            "SELECT is_excellent FROM staff
             WHERE player_id = {$player_id} AND staff_type = '{$staffDef['type']}'"
        );

        if (!$isExcellent) {
            // No effect if player doesn't have this staff
            self::notifyAllPlayers('squareLanded', clienttranslate('${player_name} lands on Staff Quits! Rolls ${staff_type} — but does not have this staff. Turn ends.'), array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'square_type' => 'staff_quits',
                'staff_type'  => $staffDef['type'],
            ));
            $this->gamestate->nextState('toEndTurn');
            return;
        }

        // Staff quits — mark as average
        self::DbQuery(
            "UPDATE staff SET is_excellent = 0
             WHERE player_id = {$player_id} AND staff_type = '{$staffDef['type']}'"
        );

        // Create auction record
        self::DbQuery(
            "INSERT INTO auction (staff_type, staff_value, source, original_owner, status)
             VALUES ('{$staffDef['type']}', {$staffDef['value']}, 'staff_quits', {$player_id}, 'active')"
        );
        $auctionId = self::DbGetLastId();
        self::setGameStateValue('auctionId', $auctionId);

        self::notifyAllPlayers('staffQuits', clienttranslate('${player_name}\'s ${staff_type} has quit! Bidding opens.'), array(
            'player_id'   => $player_id,
            'player_name' => self::getActivePlayerName(),
            'staff_type'  => $staffDef['type'],
            'staff_value' => $staffDef['value'],
            'auction_id'  => $auctionId,
        ));

        $this->gamestate->nextState('toStaffQuits');
    }

    private function initiateHelpWanted($player_id)
    {
        $result   = $this->rollStaffDieInternal();
        $staffDef = $result['staff'];

        // Check box availability
        $available = (int) self::getUniqueValueFromDB(
            "SELECT available FROM staff_box WHERE staff_type = '{$staffDef['type']}'"
        );

        if (!$available) {
            self::notifyAllPlayers('squareLanded', clienttranslate('${player_name} lands on Help Wanted! Rolls ${staff_type} — not available in the Staff Box. Turn ends.'), array(
                'player_id'   => $player_id,
                'player_name' => self::getActivePlayerName(),
                'square_type' => 'bistro_help_wanted',
                'staff_type'  => $staffDef['type'],
            ));
            $this->gamestate->nextState('toEndTurn');
            return;
        }

        self::DbQuery(
            "INSERT INTO auction (staff_type, staff_value, source, status)
             VALUES ('{$staffDef['type']}', {$staffDef['value']}, 'help_wanted', 'active')"
        );
        $auctionId = self::DbGetLastId();
        self::setGameStateValue('auctionId', $auctionId);

        self::notifyAllPlayers('helpWanted', clienttranslate('${player_name} lands on Help Wanted! ${staff_type} is available to hire.'), array(
            'player_id'   => $player_id,
            'player_name' => self::getActivePlayerName(),
            'staff_type'  => $staffDef['type'],
            'staff_value' => $staffDef['value'],
            'auction_id'  => $auctionId,
        ));

        $this->gamestate->nextState('toHelpWanted');
    }

    /**
     * stStaffQuitsBid
     * Activates all players except the tile's original owner for bidding.
     */
    function stStaffQuitsBid()
    {
        $auctionId = (int) self::getGameStateValue('auctionId');
        $auction   = self::getObjectFromDB(
            "SELECT * FROM auction WHERE auction_id = {$auctionId}"
        );

        $originalOwner = (int) $auction['original_owner'];
        $allPlayers    = self::loadPlayersBasicInfos();
        $biddingPlayers = array();

        foreach ($allPlayers as $pid => $pinfo) {
            if ((int) $pid !== $originalOwner) {
                $biddingPlayers[] = (int) $pid;
            }
        }

        if (empty($biddingPlayers)) {
            // Only one player (or no other players) — tile goes to box
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

        $this->gamestate->setPlayersMultiactive($biddingPlayers, 'toEndTurn');
    }

    /**
     * stHelpWantedBid
     * Activates all players for Help Wanted bidding.
     */
    function stHelpWantedBid()
    {
        $allPlayers    = array_keys(self::loadPlayersBasicInfos());
        $biddingPlayers = array_map('intval', $allPlayers);

        if (empty($biddingPlayers)) {
            $this->gamestate->nextState('toEndTurn');
            return;
        }

        $this->gamestate->setPlayersMultiactive($biddingPlayers, 'toEndTurn');
    }

    /**
     * stEndTurn
     * Resolves the auction (if any), checks win condition, advances to next player.
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
                    // Winner pays bank and receives tile
                    $this->adjustDuckats($winnerId, -$winAmount);

                    if ($auction['source'] === 'staff_quits') {
                        // Transfer from original owner's now-average slot → winner
                        self::DbQuery(
                            "UPDATE staff SET is_excellent = 1
                             WHERE player_id = {$winnerId}
                             AND staff_type = '{$auction['staff_type']}'"
                        );
                    } else {
                        // Help Wanted: from box to winner
                        self::DbQuery(
                            "UPDATE staff SET is_excellent = 1
                             WHERE player_id = {$winnerId}
                             AND staff_type = '{$auction['staff_type']}'"
                        );
                        self::DbQuery(
                            "UPDATE staff_box SET available = 0
                             WHERE staff_type = '{$auction['staff_type']}'"
                        );
                    }

                    self::incStat(1, 'staffBidsWon', $winnerId);

                    self::notifyAllPlayers('auctionResolved', clienttranslate('${player_name} wins the auction for ${staff_type} with a bid of ${amount} Duckats.'), array(
                        'player_id'   => $winnerId,
                        'player_name' => self::getPlayerNameById($winnerId),
                        'staff_type'  => $auction['staff_type'],
                        'amount'      => $winAmount,
                    ));
                } else {
                    // No bids — tile returns to Staff Box
                    self::DbQuery(
                        "UPDATE staff_box SET available = 1
                         WHERE staff_type = '{$auction['staff_type']}'"
                    );
                    self::notifyAllPlayers('auctionResolved', clienttranslate('No bids were placed. The staff tile returns to the Staff Box.'), array());
                }

                self::DbQuery(
                    "UPDATE auction SET status = 'resolved' WHERE auction_id = {$auctionId}"
                );
            }

            self::setGameStateValue('auctionId', 0);
        }

        // Check win condition on active player
        $activePlayerId = self::getActivePlayerId();
        if ($this->hasWon($activePlayerId)) {
            self::DbQuery(
                "UPDATE player SET player_score = 1 WHERE player_id = {$activePlayerId}"
            );
            self::notifyAllPlayers('gameWon', clienttranslate('${player_name} has hired all 12 Excellent staff and wins the game!'), array(
                'player_id'   => $activePlayerId,
                'player_name' => self::getActivePlayerName(),
            ));
            $this->gamestate->nextState('toGameEnd');
            return;
        }

        // Advance to next player (skip vacation players)
        $maxSkips = count(self::loadPlayersBasicInfos());
        for ($i = 0; $i < $maxSkips; $i++) {
            $this->activeNextPlayer();
            $nextId    = self::getActivePlayerId();
            $vacation  = (int) self::getUniqueValueFromDB(
                "SELECT player_on_vacation FROM player WHERE player_id = {$nextId}"
            );
            if ($vacation) {
                // Clear vacation flag and skip
                self::DbQuery(
                    "UPDATE player SET player_on_vacation = 0 WHERE player_id = {$nextId}"
                );
                self::notifyAllPlayers('playerSkipped', clienttranslate('${player_name} is on vacation and loses their turn.'), array(
                    'player_id'   => $nextId,
                    'player_name' => self::getPlayerNameById($nextId),
                ));
            } else {
                break;
            }
        }

        self::incStat(1, 'totalRounds', 0);
        $this->gamestate->nextState('toChooseQuestion');
    }

    // ==================================================================
    // GAME STATE ARGUMENTS
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

    // ==================================================================
    // ZOMBIE TURN
    // ==================================================================

    function zombieTurn($state, $active_player)
    {
        $statename = $state['name'];

        if ($state['type'] === 'activeplayer') {
            switch ($statename) {
                case 'chooseQuestion':
                    // Auto-choose A
                    $this->chooseLetter('A');
                    break;
                case 'answerQuestion':
                    // Auto-submit wrong answer (no Duckats awarded)
                    $this->gamestate->nextState('toRollStaffDie');
                    break;
                case 'rollStaffDie':
                    $this->rollStaffDie();
                    break;
                case 'rollMovement':
                    $this->rollMovement();
                    break;
                case 'resolveRestaurant':
                    $this->gamestate->nextState('toEndTurn');
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
    // UPGRADE
    // ==================================================================

    function upgradeTableDb($from_version)
    {
        // Future schema migrations go here
    }
}
