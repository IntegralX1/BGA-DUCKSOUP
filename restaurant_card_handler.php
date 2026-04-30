<?php
/**
 * Duck Soup: The Restaurant Game
 * restaurant_card_handler.php
 *
 * Contains:
 *   - BOARD_SQUARES constant: authoritative 32-square clockwise map (sq 0–31)
 *   - DuckSoupCardHandler class: resolveRestaurantCard() dispatcher and helpers
 *
 * Usage in game.php:
 *   require_once('restaurant_card_handler.php');
 *
 * resolveRestaurantCard() is called from the stRestaurantCard game state action.
 * It reads the drawn card's card_type, applies the effect, and returns an array
 * describing what happened so the front-end can be notified.
 *
 * Board square types (used by Go-to-Next logic):
 *   duck_soup, restaurant, staff_quits, help_wanted,
 *   hire_kitchen, hire_dining_room, hire_kitchen_dining,
 *   vacation, business_great, renos_repairs
 */

// =============================================================
// BOARD SQUARE MAP
// Authoritative clockwise sequence, square 0 = Duck Soup (bottom-left corner).
// Index = board position stored in player_board_position.
// =============================================================

define('DUCK_SOUP_BOARD_SQUARES', [
    0  => 'duck_soup',           // Bottom-left corner
    1  => 'restaurant',
    2  => 'staff_quits',
    3  => 'help_wanted',
    4  => 'restaurant',
    5  => 'staff_quits',
    6  => 'hire_dining_room',
    7  => 'restaurant',
    8  => 'vacation',            // Bottom-right corner
    9  => 'hire_kitchen',
    10 => 'restaurant',
    11 => 'help_wanted',
    12 => 'restaurant',
    13 => 'staff_quits',
    14 => 'hire_kitchen_dining',
    15 => 'restaurant',
    16 => 'business_great',      // Top-right corner
    17 => 'restaurant',          // Upside-down in physical board image
    18 => 'staff_quits',         // Upside-down in physical board image
    19 => 'help_wanted',         // Upside-down in physical board image
    20 => 'restaurant',          // Upside-down in physical board image
    21 => 'staff_quits',         // Upside-down in physical board image
    22 => 'hire_dining_room',    // Upside-down in physical board image
    23 => 'restaurant',          // Upside-down in physical board image
    24 => 'renos_repairs',       // Top-left corner
    25 => 'hire_kitchen',
    26 => 'restaurant',
    27 => 'help_wanted',
    28 => 'restaurant',
    29 => 'staff_quits',
    30 => 'hire_kitchen_dining',
    31 => 'restaurant',
]);

define('DUCK_SOUP_BOARD_SIZE', 32);

// =============================================================
// CARD HANDLER CLASS
// =============================================================

class DuckSoupCardHandler
{
    /**
     * Main dispatcher. Called when a player draws a Restaurant card.
     *
     * Reads the top card (lowest card_order where used=0 equivalent —
     * cards are cycled by moving them to the bottom via card_order update),
     * dispatches to the appropriate handler, and returns a result array.
     *
     * Result array shape:
     * [
     *   'card_type'        => string,   // e.g. 'plumbing_problems'
     *   'description'      => string,   // Card text shown to players
     *   'effect'           => string,   // 'pay'|'collect'|'movement'|'auction'|
     *                                   //  'hire'|'shuffle'|'all_pay'|'all_collect'|
     *                                   //  'roll_pay'|'roll_collect'|'all_roll_pay'|
     *                                   //  'critic'|'vacation'|'conditional_hire'|'none'
     *   'amount'           => int,      // Flat Duckat amount (0 if roll/conditional)
     *   'move_to_square'   => int|null, // Target square index for movement cards
     *   'needs_roll'       => bool,     // True if a dice roll is still required
     *   'needs_hire'       => bool,     // True if a hire choice UI is needed
     *   'hire_type'        => string|null, // 'kitchen'|'dining_room'|'either' (half_price hires)
     *   'hire_half_price'  => bool,     // True if the hire is at half price
     *   'requires_staff'   => string|null, // Staff type required for conditional cards
     *   'critic_payouts'   => array,    // player_id => duckat_amount (critic cards only)
     *   'all_players'      => bool,     // True if effect applies to all players
     * ]
     *
     * @param  object $game         BGA game object.
     * @param  int    $activePlayerId
     * @return array  Result array as described above.
     */
    public static function resolveRestaurantCard(object $game, int $activePlayerId): array
    {
        // Draw the top card (lowest card_order)
        $card = $game->getObjectFromDB(
            "SELECT * FROM restaurant_card ORDER BY card_order ASC LIMIT 1"
        );

        if (!$card) {
            // Deck is empty — reshuffle and draw again
            self::reshuffleDeck($game);
            $card = $game->getObjectFromDB(
                "SELECT * FROM restaurant_card ORDER BY card_order ASC LIMIT 1"
            );
        }

        // Move this card to the bottom of the deck (place-at-bottom cycling)
        $maxOrder = $game->getUniqueValueFromDB(
            "SELECT MAX(card_order) FROM restaurant_card"
        );
        $game->DbQuery(
            "UPDATE restaurant_card SET card_order = " . ((int)$maxOrder + 1) .
            " WHERE card_id = " . (int)$card['card_id']
        );

        $cardType    = $card['card_type'];
        $description = $card['description'];
        $effectValue = (int)$card['effect_value'];
        $effectJson  = $card['effect_json'];

        // Base result — overridden by each handler below
        $result = [
            'card_id'          => (int)$card['card_id'],
            'card_type'        => $cardType,
            'description'      => $description,
            'effect'           => 'none',
            'amount'           => 0,
            'move_to_square'   => null,
            'needs_roll'       => false,
            'needs_hire'       => false,
            'hire_type'        => null,
            'hire_half_price'  => false,
            'requires_staff'   => null,
            'critic_payouts'   => [],
            'all_players'      => false,
        ];

        switch ($cardType) {

            // -------------------------------------------------------
            // FLAT PAY — active player pays fixed amount
            // -------------------------------------------------------
            case 'plumbing_problems':
            case 'dishwasher_breaks':
            case 'air_conditioning':
            case 'theft_wine':
            case 'road_construction':
            case 'theft_kitchen':
            case 'smallware_costs':
                $result['effect'] = 'pay';
                $result['amount'] = abs($effectValue);
                self::adjustDuckats($game, $activePlayerId, $effectValue);
                break;

            // -------------------------------------------------------
            // FLAT COLLECT — active player collects fixed amount
            // -------------------------------------------------------
            case 'convention':
            case 'competitor_bankrupt':
                $result['effect'] = 'collect';
                $result['amount'] = $effectValue;
                self::adjustDuckats($game, $activePlayerId, $effectValue);
                break;

            // -------------------------------------------------------
            // ALL PLAYERS COLLECT — flat amount, every player
            // -------------------------------------------------------
            case 'mothers_day':
                $result['effect']      = 'all_collect';
                $result['amount']      = $effectValue;
                $result['all_players'] = true;
                $players = $game->loadPlayersBasicInfos();
                foreach ($players as $playerId => $player) {
                    self::adjustDuckats($game, (int)$playerId, $effectValue);
                }
                break;

            // -------------------------------------------------------
            // ROLL-BASED PAY — active player rolls dice, pays 5× roll
            // -------------------------------------------------------
            case 'renos_repairs':
                $result['effect']     = 'roll_pay';
                $result['needs_roll'] = true;
                // Actual deduction applied in applyRollEffect() after dice roll
                break;

            // -------------------------------------------------------
            // ROLL-BASED COLLECT — active player rolls dice, collects 5× roll
            // -------------------------------------------------------
            case 'business_great':
                $result['effect']     = 'roll_collect';
                $result['needs_roll'] = true;
                break;

            // -------------------------------------------------------
            // ALL PLAYERS ROLL-PAY — one roll, all players pay 5× roll
            // -------------------------------------------------------
            case 'food_costs_jump':
                $result['effect']      = 'all_roll_pay';
                $result['needs_roll']  = true;
                $result['all_players'] = true;
                break;

            // -------------------------------------------------------
            // CRITIC CARDS — tiered payout per player based on staff value
            // -------------------------------------------------------
            case 'critic_olive':
            case 'critic_corky':
            case 'critic_riley':
                $result['effect']      = 'critic';
                $result['all_players'] = true;
                $payouts = self::resolveCriticAllPlayers($game, $effectJson);
                $result['critic_payouts'] = $payouts;
                foreach ($payouts as $playerId => $amount) {
                    self::adjustDuckats($game, (int)$playerId, $amount);
                }
                break;

            // -------------------------------------------------------
            // VACATION — active player loses next turn
            // -------------------------------------------------------
            case 'vacation':
                $result['effect'] = 'vacation';
                $game->DbQuery(
                    "UPDATE player SET player_on_vacation = 1
                     WHERE player_id = " . (int)$activePlayerId
                );
                break;

            // -------------------------------------------------------
            // SHUFFLE DECK
            // -------------------------------------------------------
            case 'shuffle_deck':
                $result['effect'] = 'shuffle';
                self::reshuffleDeck($game);
                break;

            // -------------------------------------------------------
            // MOVEMENT — go back / forward one square
            // -------------------------------------------------------
            case 'go_back_one':
                $currentPos = self::getPlayerPosition($game, $activePlayerId);
                $newPos     = ($currentPos - 1 + DUCK_SOUP_BOARD_SIZE) % DUCK_SOUP_BOARD_SIZE;
                $result['effect']        = 'movement';
                $result['amount']        = -1;
                $result['move_to_square'] = $newPos;
                self::setPlayerPosition($game, $activePlayerId, $newPos);
                break;

            case 'go_forward_one':
                $currentPos = self::getPlayerPosition($game, $activePlayerId);
                $newPos     = ($currentPos + 1) % DUCK_SOUP_BOARD_SIZE;
                $result['effect']        = 'movement';
                $result['amount']        = 1;
                $result['move_to_square'] = $newPos;
                self::setPlayerPosition($game, $activePlayerId, $newPos);
                break;

            // -------------------------------------------------------
            // GO TO NEXT — advance to next occurrence of square type
            // -------------------------------------------------------
            case 'go_to_next_hire_kitchen':
                $targetPos = self::findNextSquare($game, $activePlayerId, 'hire_kitchen');
                $result['effect']        = 'movement';
                $result['move_to_square'] = $targetPos;
                self::setPlayerPosition($game, $activePlayerId, $targetPos);
                break;

            case 'go_to_next_hire_dining':
                $targetPos = self::findNextSquare($game, $activePlayerId, 'hire_dining_room');
                $result['effect']        = 'movement';
                $result['move_to_square'] = $targetPos;
                self::setPlayerPosition($game, $activePlayerId, $targetPos);
                break;

            case 'go_to_next_hire_either':
                $targetPos = self::findNextSquareAny(
                    $game, $activePlayerId, ['hire_kitchen', 'hire_dining_room', 'hire_kitchen_dining']
                );
                $result['effect']        = 'movement';
                $result['move_to_square'] = $targetPos;
                self::setPlayerPosition($game, $activePlayerId, $targetPos);
                break;

            case 'go_to_next_staff_quits':
                $targetPos = self::findNextSquare($game, $activePlayerId, 'staff_quits');
                $result['effect']        = 'movement';
                $result['move_to_square'] = $targetPos;
                self::setPlayerPosition($game, $activePlayerId, $targetPos);
                break;

            // -------------------------------------------------------
            // CONDITIONAL HIRE — requires specific staff; half price
            // -------------------------------------------------------
            case 'chef_cook_bonus':
                $result['effect']         = 'conditional_hire';
                $result['requires_staff'] = 'chef';
                if (self::playerHasExcellentStaff($game, $activePlayerId, 'chef')) {
                    $result['needs_hire']      = true;
                    $result['hire_type']       = 'kitchen';
                    $result['hire_half_price'] = true;
                }
                // If player has no Excellent Chef, needs_hire stays false — turn ends
                break;

            case 'maitre_d_bonus':
                $result['effect']         = 'conditional_hire';
                $result['requires_staff'] = 'maitre_d';
                if (self::playerHasExcellentStaff($game, $activePlayerId, 'maitre_d')) {
                    $result['needs_hire']      = true;
                    $result['hire_type']       = 'dining_room';
                    $result['hire_half_price'] = true;
                }
                break;

            case 'chef_on_tv':
                // Collect 15 if player has Excellent Chef; otherwise nothing
                if (self::playerHasExcellentStaff($game, $activePlayerId, 'chef')) {
                    $result['effect'] = 'collect';
                    $result['amount'] = $effectValue; // 15
                    self::adjustDuckats($game, $activePlayerId, $effectValue);
                } else {
                    $result['effect'] = 'conditional_hire'; // re-used for "condition not met"
                    $result['requires_staff'] = 'chef';
                }
                break;
        }

        return $result;
    }

    // =============================================================
    // applyRollEffect()
    // Called AFTER the dice roll completes for needs_roll cards.
    // $diceTotal = sum of the two dice.
    // Returns the Duckat amount applied.
    // =============================================================

    /**
     * @param  object $game
     * @param  string $cardType       The card_type that triggered the roll.
     * @param  int    $diceTotal      Sum of both dice (2–12).
     * @param  int    $activePlayerId
     * @return int    Absolute Duckat amount applied.
     */
    public static function applyRollEffect(
        object $game,
        string $cardType,
        int    $diceTotal,
        int    $activePlayerId
    ): int {
        $amount = $diceTotal * 5;

        switch ($cardType) {
            case 'renos_repairs':
                // Active player pays
                self::adjustDuckats($game, $activePlayerId, -$amount);
                break;

            case 'business_great':
                // Active player collects
                self::adjustDuckats($game, $activePlayerId, $amount);
                break;

            case 'food_costs_jump':
                // All players pay
                $players = $game->loadPlayersBasicInfos();
                foreach ($players as $playerId => $player) {
                    self::adjustDuckats($game, (int)$playerId, -$amount);
                }
                break;
        }

        return $amount;
    }

    // =============================================================
    // PRIVATE HELPERS
    // =============================================================

    /**
     * Adjust a player's Duckat balance. Enforces floor of 0 — a player
     * cannot go below zero (they must sell staff instead, handled by UI).
     */
    private static function adjustDuckats(object $game, int $playerId, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        if ($delta > 0) {
            $game->DbQuery(
                "UPDATE player
                 SET player_duckats = player_duckats + {$delta}
                 WHERE player_id = {$playerId}"
            );
        } else {
            $absDelta = abs($delta);
            // Floor at 0 — if player cannot pay, balance is set to 0.
            // Selling staff to cover the shortfall is handled separately in game.php.
            $game->DbQuery(
                "UPDATE player
                 SET player_duckats = GREATEST(0, CAST(player_duckats AS SIGNED) - {$absDelta})
                 WHERE player_id = {$playerId}"
            );
        }
    }

    /**
     * Reshuffle the restaurant card deck by reassigning random card_order values.
     */
    private static function reshuffleDeck(object $game): void
    {
        $cards = $game->getObjectListFromDB(
            "SELECT card_id FROM restaurant_card",
            true
        );
        $ids   = array_values($cards);
        $order = range(1, count($ids));
        shuffle($order);

        foreach ($ids as $i => $cardId) {
            $game->DbQuery(
                "UPDATE restaurant_card SET card_order = " . (int)$order[$i] .
                " WHERE card_id = " . (int)$cardId
            );
        }
    }

    /**
     * Get a player's current board position.
     */
    private static function getPlayerPosition(object $game, int $playerId): int
    {
        return (int)$game->getUniqueValueFromDB(
            "SELECT player_board_position FROM player WHERE player_id = {$playerId}"
        );
    }

    /**
     * Set a player's board position.
     */
    private static function setPlayerPosition(object $game, int $playerId, int $position): void
    {
        $game->DbQuery(
            "UPDATE player SET player_board_position = {$position}
             WHERE player_id = {$playerId}"
        );
    }

    /**
     * Find the next occurrence of a single square type clockwise from
     * the player's current position (exclusive — never returns current square).
     *
     * @param  string $squareType  A DUCK_SOUP_BOARD_SQUARES type string.
     * @return int    Target square index.
     * @throws \BgaVisibleSystemException if square type not found on board.
     */
    private static function findNextSquare(object $game, int $playerId, string $squareType): int
    {
        $current = self::getPlayerPosition($game, $playerId);
        $board   = DUCK_SOUP_BOARD_SQUARES;
        $size    = DUCK_SOUP_BOARD_SIZE;

        for ($offset = 1; $offset < $size; $offset++) {
            $check = ($current + $offset) % $size;
            if ($board[$check] === $squareType) {
                return $check;
            }
        }

        throw new \BgaVisibleSystemException(
            "Board square type '{$squareType}' not found — board map may be misconfigured."
        );
    }

    /**
     * Find the next square matching ANY of the given types (for hire_either).
     *
     * @param  string[] $squareTypes
     * @return int Target square index.
     */
    private static function findNextSquareAny(
        object $game,
        int    $playerId,
        array  $squareTypes
    ): int {
        $current = self::getPlayerPosition($game, $playerId);
        $board   = DUCK_SOUP_BOARD_SQUARES;
        $size    = DUCK_SOUP_BOARD_SIZE;

        for ($offset = 1; $offset < $size; $offset++) {
            $check = ($current + $offset) % $size;
            if (in_array($board[$check], $squareTypes, true)) {
                return $check;
            }
        }

        throw new \BgaVisibleSystemException(
            "No hire square found on board — board map may be misconfigured."
        );
    }

    /**
     * Check whether a player currently has a specific Excellent staff member.
     *
     * @param  string $staffType  e.g. 'chef', 'maitre_d'
     */
    private static function playerHasExcellentStaff(
        object $game,
        int    $playerId,
        string $staffType
    ): bool {
        $count = (int)$game->getUniqueValueFromDB(
            "SELECT COUNT(*) FROM staff
             WHERE player_id   = {$playerId}
             AND   staff_type  = '" . addslashes($staffType) . "'
             AND   is_excellent = 1"
        );
        return $count > 0;
    }

    /**
     * Calculate Critic card payouts for all players.
     * Uses the JSON grid stored on the card.
     *
     * @param  string $effectJson  JSON string from restaurant_card.effect_json
     * @return array  player_id (int) => duckat_amount (int)
     */
    private static function resolveCriticAllPlayers(object $game, string $effectJson): array
    {
        $grid    = json_decode($effectJson, true);
        $players = $game->loadPlayersBasicInfos();
        $payouts = [];

        foreach ($players as $playerId => $player) {
            // Sum all Excellent staff values for this player
            $totalValue = (int)$game->getUniqueValueFromDB(
                "SELECT COALESCE(SUM(staff_value), 0)
                 FROM staff
                 WHERE player_id   = {$playerId}
                 AND   is_excellent = 1"
            );

            $payout = 0;
            foreach ($grid as $range => $amount) {
                [$min, $max] = explode('-', $range);
                if ($totalValue >= (int)$min && $totalValue <= (int)$max) {
                    $payout = (int)$amount;
                    break;
                }
            }

            $payouts[(int)$playerId] = $payout;
        }

        return $payouts;
    }
}
