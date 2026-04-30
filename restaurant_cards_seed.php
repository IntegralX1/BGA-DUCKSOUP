<?php
/**
 * Duck Soup: The Restaurant Game
 * restaurant_cards_seed.php
 *
 * Seeds the restaurant_card table with all 30 cards from the physical deck.
 * Called once from setupNewGame() after the table is created.
 *
 * Card types reference:
 *   go_to_next_hire_kitchen   — move to next Kitchen square
 *   go_to_next_hire_dining    — move to next Dining Room square
 *   go_to_next_hire_either    — move to next Kitchen or Dining Room square
 *   go_to_next_staff_quits    — move to next Staff Quits square
 *   critic_olive              — Olive McDoyle review (tiered all-player payout)
 *   critic_corky              — Corky Weinberg review (tiered all-player payout)
 *   critic_riley              — Riley Baker review (tiered all-player payout)
 *   food_costs_jump           — roll dice, all players pay 5× roll
 *   plumbing_problems         — pay 20
 *   dishwasher_breaks         — pay 20
 *   air_conditioning          — pay 20
 *   theft_wine                — pay 15
 *   road_construction         — pay 15
 *   theft_kitchen             — pay 15
 *   smallware_costs           — pay 10
 *   mothers_day               — all players collect 10
 *   convention                — collect 15
 *   competitor_bankrupt       — collect 25
 *   maitre_d_bonus            — hire server half price (requires Excellent Maître d')
 *   chef_cook_bonus           — hire cook half price (requires Excellent Chef)
 *   chef_on_tv                — collect 15 (requires Excellent Chef)
 *   shuffle_deck              — reshuffle the restaurant card deck
 *   go_back_one               — move back 1 square (×3 copies)
 *   go_forward_one            — move forward 1 square (×3 copies)
 *   vacation                  — lose next turn
 *   renos_repairs             — roll dice, active player pays 5× roll
 *   business_great            — roll dice, active player collects 5× roll
 *
 * effect_value conventions:
 *   negative = pay into Bank
 *   positive = collect from Bank
 *   0        = no flat amount (roll-based, movement, or JSON-grid cards)
 *
 * effect_json: populated only for Critic cards.
 *   Keys are "minStaffValue-maxStaffValue" strings.
 *   Values are Duckat amounts all players collect.
 *   Game logic iterates keys in order; first matching range wins.
 */

class DuckSoupRestaurantCards
{
    /**
     * Returns the full ordered card definitions.
     * card_order is assigned after shuffling in seedCards().
     *
     * @return array[]
     */
    public static function getCards(): array
    {
        return [
            // --- Movement cards (Go to Next square) ---
            [
                'card_type'   => 'go_to_next_hire_kitchen',
                'description' => 'Go to the next Kitchen square.',
                'effect_value' => 0,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'go_to_next_hire_dining',
                'description' => 'Go to the next Dining Room square.',
                'effect_value' => 0,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'go_to_next_hire_either',
                'description' => 'Go to the next Kitchen or Dining Room square.',
                'effect_value' => 0,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'go_to_next_staff_quits',
                'description' => 'Go to the next Staff Quits square.',
                'effect_value' => 0,
                'effect_json' => null,
            ],

            // --- Critic cards (all players collect; tiered by total Excellent staff value) ---
            [
                'card_type'   => 'critic_olive',
                'description' => 'Dining Out with Olive McDoyle. All players add up their Excellent staff values and collect Duckats from the Bank as per the grid.',
                'effect_value' => 0,
                'effect_json' => json_encode([
                    '0-80'   => 10,
                    '90-180' => 20,
                    '190-310'=> 30,
                    '320-410'=> 40,
                    '420-500'=> 50,
                ]),
            ],
            [
                'card_type'   => 'critic_corky',
                'description' => 'Uptown Magazine: Corky Weinberg Review. All players add up their Excellent staff values and collect Duckats from the Bank as per the grid.',
                'effect_value' => 0,
                'effect_json' => json_encode([
                    '0-80'   => 10,
                    '90-250' => 25,
                    '260-410'=> 40,
                    '420-500'=> 60,
                ]),
            ],
            [
                'card_type'   => 'critic_riley',
                'description' => 'Daily Blab: Riley Baker Says. All players add up their Excellent staff values and collect Duckats from the Bank as per the grid.',
                'effect_value' => 0,
                'effect_json' => json_encode([
                    '0-80'   => 10,
                    '90-170' => 20,
                    '180-260'=> 30,
                    '270-330'=> 40,
                    '340-430'=> 50,
                    '440-500'=> 60,
                ]),
            ],

            // --- All-player pay cards ---
            [
                'card_type'   => 'food_costs_jump',
                'description' => 'Food costs jump! Roll the dice. All players pay 5 times this roll in Duckats into the Bank.',
                'effect_value' => 0,   // amount determined by dice roll at resolution time
                'effect_json' => null,
            ],

            // --- Active-player pay cards ---
            [
                'card_type'   => 'plumbing_problems',
                'description' => 'Plumbing problems. Pay 20 Duckats.',
                'effect_value' => -20,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'dishwasher_breaks',
                'description' => 'Dishwasher breaks down. Pay 20 Duckats.',
                'effect_value' => -20,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'air_conditioning',
                'description' => 'Air conditioning breaks down. Pay 20 Duckats.',
                'effect_value' => -20,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'theft_wine',
                'description' => 'Theft from the wine cellar. Pay 15 Duckats.',
                'effect_value' => -15,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'road_construction',
                'description' => 'Road construction in front of your restaurant. Pay 15 Duckats.',
                'effect_value' => -15,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'theft_kitchen',
                'description' => 'Theft from the kitchen. Pay 15 Duckats.',
                'effect_value' => -15,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'smallware_costs',
                'description' => 'Smallware costs. Pay 10 Duckats.',
                'effect_value' => -10,
                'effect_json' => null,
            ],

            // --- All-player collect cards ---
            [
                'card_type'   => 'mothers_day',
                'description' => "Mother's Day. All players collect 10 Duckats.",
                'effect_value' => 10,
                'effect_json' => null,
            ],

            // --- Active-player collect cards ---
            [
                'card_type'   => 'convention',
                'description' => 'Convention in town. Increased business. Collect 15 Duckats.',
                'effect_value' => 15,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'competitor_bankrupt',
                'description' => 'Nearby competitor goes bankrupt. Collect 25 Duckats.',
                'effect_value' => 25,
                'effect_json' => null,
            ],

            // --- Conditional hire bonus cards ---
            [
                'card_type'   => 'maitre_d_bonus',
                'description' => "If you have an Excellent Maître d', you may hire a server for half price.",
                'effect_value' => 0,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'chef_cook_bonus',
                'description' => 'If you have an Excellent Chef, you may hire a cook for half price.',
                'effect_value' => 0,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'chef_on_tv',
                'description' => 'If you have an Excellent Chef, he appears on TV. Great advertising. Collect 15 Duckats.',
                'effect_value' => 15,
                'effect_json' => null,
            ],

            // --- Deck management ---
            [
                'card_type'   => 'shuffle_deck',
                'description' => 'Shuffle the Restaurant card deck.',
                'effect_value' => 0,
                'effect_json' => null,
            ],

            // --- Movement: go back (×3) ---
            [
                'card_type'   => 'go_back_one',
                'description' => 'Go back one square.',
                'effect_value' => -1,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'go_back_one',
                'description' => 'Go back one square.',
                'effect_value' => -1,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'go_back_one',
                'description' => 'Go back one square.',
                'effect_value' => -1,
                'effect_json' => null,
            ],

            // --- Movement: go forward (×3) ---
            [
                'card_type'   => 'go_forward_one',
                'description' => 'Go forward one square.',
                'effect_value' => 1,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'go_forward_one',
                'description' => 'Go forward one square.',
                'effect_value' => 1,
                'effect_json' => null,
            ],
            [
                'card_type'   => 'go_forward_one',
                'description' => 'Go forward one square.',
                'effect_value' => 1,
                'effect_json' => null,
            ],

            // --- Vacation ---
            [
                'card_type'   => 'vacation',
                'description' => 'Lose your next turn. You may not enter into any bidding while on vacation.',
                'effect_value' => 0,
                'effect_json' => null,
            ],

            // --- Roll-based pay/collect (card deck versions) ---
            [
                'card_type'   => 'renos_repairs',
                'description' => 'Renos and repairs. Roll the dice and pay 5 times the roll in Duckats into the Bank.',
                'effect_value' => 0,   // amount determined by dice roll at resolution time
                'effect_json' => null,
            ],
            [
                'card_type'   => 'business_great',
                'description' => 'Business is great! Roll the dice and collect 5 times the roll in Duckats from the Bank.',
                'effect_value' => 0,   // amount determined by dice roll at resolution time
                'effect_json' => null,
            ],
        ];
    }

    /**
     * Seeds the restaurant_card table for a new game instance.
     * Truncates any existing rows, assigns a shuffled card_order,
     * then bulk-inserts all 30 cards.
     *
     * Usage in setupNewGame():
     *   require_once('restaurant_cards_seed.php');
     *   DuckSoupRestaurantCards::seedCards($this);
     *
     * @param  Table $game  The BGA game object (provides DbQuery / DbGetObjectList).
     * @return void
     */
    public static function seedCards(object $game): void
    {
        // Clear any existing cards for this game instance
        $game->DbQuery("DELETE FROM restaurant_card");

        $cards = self::getCards();
        $count = count($cards);  // 30

        // Build a shuffled order array (1-based)
        $order = range(1, $count);
        shuffle($order);

        $values = [];
        foreach ($cards as $i => $card) {
            $cardOrder  = (int) $order[$i];
            $cardType   = addslashes($card['card_type']);
            $description = addslashes($card['description']);
            $effectValue = (int) $card['effect_value'];
            $effectJson  = $card['effect_json'] !== null
                ? "'" . addslashes($card['effect_json']) . "'"
                : 'NULL';

            $values[] = "('{$cardType}', '{$description}', {$effectValue}, {$effectJson}, {$cardOrder})";
        }

        $sql = "INSERT INTO restaurant_card
                    (card_type, description, effect_value, effect_json, card_order)
                VALUES " . implode(', ', $values);

        $game->DbQuery($sql);
    }

    /**
     * Resolves a Critic card payout for a single player.
     * Parses the effect_json grid and returns the Duckat amount
     * the player should collect based on their total Excellent staff value.
     *
     * @param  string $effectJson   The effect_json column value from the DB row.
     * @param  int    $totalStaffValue  Sum of all Excellent staff values the player owns.
     * @return int    Duckats to collect (0 if no tier matched).
     */
    public static function resolveCriticPayout(string $effectJson, int $totalStaffValue): int
    {
        $grid = json_decode($effectJson, true);
        if (!is_array($grid)) {
            return 0;
        }

        foreach ($grid as $range => $payout) {
            [$min, $max] = explode('-', $range);
            if ($totalStaffValue >= (int)$min && $totalStaffValue <= (int)$max) {
                return (int)$payout;
            }
        }

        return 0;
    }
}
