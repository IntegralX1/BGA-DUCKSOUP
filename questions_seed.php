<?php
/**
 * questions_seed.php
 *
 * Duck Soup — Question Card Seeder
 *
 * Loads all trivia questions from the embedded data array into the
 * `question` table for this game instance. Called once from
 * setupNewGame(). Each game instance gets its own isolated copy of
 * the shuffled deck so concurrent games never interfere.
 *
 * Data provenance:
 *   - Source: questions.csv (900 rows)
 *   - 15 rows with correct_answer='err' are excluded (no valid answer)
 *   - Category casing is normalized to Title Case
 *   - Deck order is randomised with shuffle() before insert
 *
 * Usage inside setupNewGame():
 *   require_once('ducksouptherestaurantgamequestions_seed.php');
 *   duckSoupTheRestaurantGameQuestions::seed($this);
 */

class duckSoupTheRestaurantGameQuestions
{
    /**
     * Seed the question table for a new game instance.
     *
     * @param Table $game  The BGA game object (provides DbQuery / escapeStringForDB)
     */
    public static function seed($game)
    {
        $questions = self::getData();

        // Shuffle to randomise draw order for this game instance
        shuffle($questions);

        // Bulk insert in one transaction — BGA Studio MySQL supports multi-row INSERT
        $values = array();
        foreach ($questions as $order => $q) {
            $values[] = sprintf(
                "(%d, '%s', '%s', '%s', '%s', %s, %s, '%s', %s, %d)",
                (int) $q['duckats_value'],
                $game->escapeStringForDB($q['category']),
                $game->escapeStringForDB($q['question_text']),
                $game->escapeStringForDB($q['answer_a']),
                $game->escapeStringForDB($q['answer_b']),
                $q['answer_c'] === null ? 'NULL' : "'" . $game->escapeStringForDB($q['answer_c']) . "'",
                $q['answer_d'] === null ? 'NULL' : "'" . $game->escapeStringForDB($q['answer_d']) . "'",
                $game->escapeStringForDB($q['correct_answer']),
                $q['answer_text'] === null ? 'NULL' : "'" . $game->escapeStringForDB($q['answer_text']) . "'",
                $order + 1   // card_order: 1-based shuffled position
            );
        }

        if (empty($values)) {
            throw new BgaVisibleSystemException("Question seeder produced no rows — check questions_seed.php data.");
        }

        $sql = "INSERT INTO question
                    (duckats_value, category, question_text, answer_a, answer_b,
                     answer_c, answer_d, correct_answer, answer_text, card_order)
                VALUES " . implode(',', $values);

        $game->DbQuery($sql);
    }

    /**
     * Draw the next unused question card from the deck.
     * Marks it as used. Returns null if the deck is exhausted
     * (caller should reshuffle or handle gracefully).
     *
     * @param  Table $game
     * @return array|null  Associative array of question fields, or null
     */
    public static function drawNext($game)
    {
        $sql = "SELECT * FROM question
                WHERE used = 0
                ORDER BY card_order ASC
                LIMIT 1";

        $row = $game->getObjectFromDB($sql);

        if ($row === null) {
            // Deck exhausted — reset and reshuffle
            self::reshuffle($game);
            $row = $game->getObjectFromDB($sql);
        }

        if ($row !== null) {
            $game->DbQuery("UPDATE question SET used = 1 WHERE question_id = " . (int)$row['question_id']);
        }

        return $row;
    }

    /**
     * Reset the deck and assign new random card_order values.
     * Called automatically when the deck runs out.
     *
     * @param Table $game
     */
    private static function reshuffle($game)
    {
        // Collect all IDs and assign a new random order
        $rows = $game->getCollectionFromDB("SELECT question_id FROM question");
        $ids  = array_keys($rows);
        shuffle($ids);

        $game->DbQuery("UPDATE question SET used = 0");

        foreach ($ids as $order => $id) {
            $game->DbQuery(
                "UPDATE question SET card_order = " . ((int)$order + 1) .
                " WHERE question_id = " . (int)$id
            );
        }
    }

    // ----------------------------------------------------------------
    // Question data (885 valid rows — 15 'err' rows excluded)
    // Generated from questions.csv with category normalization applied.
    // ----------------------------------------------------------------
    private static function getData()
    {
        return array(
            // FORMAT: duckats_value, category, question_text, answer_a, answer_b, answer_c, answer_d, correct_answer, answer_text
            // answer_c / answer_d are null for True/False and 3-choice questions respectively

            // --- BAKING (30pt T/F) ---
            array('duckats_value'=>30,'category'=>'Baking','question_text'=>'True or false? Baking at high altitudes requires more yeast or baking powder.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'It requires less yeast or baking powder. The higher the altitude, the lower the atmospheric pressure, which means that the carbon dioxide generated in baking encounters less resistance from the surrounding air.'),
            array('duckats_value'=>30,'category'=>'Baking','question_text'=>'True or false? Empanada is a type of stuffed-meat pastry.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'In Spain, empanadas are round and larger than the semicircular South American versions.'),
            array('duckats_value'=>30,'category'=>'Baking','question_text'=>'True or false? Grissini are thin smoked sausages.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Grissini are Italian bread sticks.'),
            array('duckats_value'=>30,'category'=>'Baking','question_text'=>'True or false? Pumpernickel bread is made with rye flour.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Pumpernickel was originally made in Westphalia but is now made all over Germany.'),
            array('duckats_value'=>30,'category'=>'Baking','question_text'=>'True or false? Stollen is a kind of German almond cookie.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Stollen is a German yeast bread made with dried fruit and topped with sugar icing and candied cherries. It is traditionally eaten at Christmas.'),
            array('duckats_value'=>30,'category'=>'Baking','question_text'=>'True or false? Brioche is made with a large proportion of eggs and butter.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Brioche is a highly enriched French pastry bread.'),
            array('duckats_value'=>30,'category'=>'Baking','question_text'=>'True or false? A croissant is a type of sourdough bread.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'A croissant is a buttery, flaky, viennoiserie pastry made from laminated yeast-leavened dough.'),
            array('duckats_value'=>30,'category'=>'Baking','question_text'=>'True or false? Focaccia is an Italian flatbread.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Focaccia is often topped with olive oil, herbs, and sometimes olives or tomatoes.'),
            array('duckats_value'=>30,'category'=>'Baking','question_text'=>'True or false? Baguette is a type of French bread known for its short, stubby shape.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'A baguette is known for its long, thin shape.'),
            array('duckats_value'=>30,'category'=>'Baking','question_text'=>'True or false? Ciabatta is an Italian white bread made from wheat flour, water, salt, and yeast.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Ciabatta means "slipper" in Italian, referring to its flat, elongated shape.'),

            // --- BAKING (40pt MC3) ---
            array('duckats_value'=>40,'category'=>'Baking','question_text'=>'What ingredient makes bread rise?','answer_a'=>'Baking soda','answer_b'=>'Yeast','answer_c'=>'Salt','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Yeast produces carbon dioxide gas through fermentation, which causes dough to rise.'),
            array('duckats_value'=>40,'category'=>'Baking','question_text'=>'What is the main flour used in traditional pasta?','answer_a'=>'Rice flour','answer_b'=>'Semolina','answer_c'=>'Cornflour','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Semolina is a coarse flour made from durum wheat and is used in traditional pasta making.'),
            array('duckats_value'=>40,'category'=>'Baking','question_text'=>'What gives sourdough bread its distinctive sour taste?','answer_a'=>'Vinegar','answer_b'=>'Salt','answer_c'=>'Lactic acid from fermentation','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'Sourdough fermentation produces lactic and acetic acids, giving the bread its sour flavour.'),
            array('duckats_value'=>40,'category'=>'Baking','question_text'=>'What is the purpose of proofing dough?','answer_a'=>'To add flavour','answer_b'=>'To allow yeast to produce gas and dough to rise','answer_c'=>'To cool the dough','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Proofing (or proving) is the final rise of shaped bread dough before baking.'),
            array('duckats_value'=>40,'category'=>'Baking','question_text'=>'What is blind baking?','answer_a'=>'Baking without looking at the oven','answer_b'=>'Baking a pastry shell without filling','answer_c'=>'Baking in the dark','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Blind baking involves baking a pastry crust before adding filling, preventing a soggy bottom.'),

            // --- BAKING (50pt MC4) ---
            array('duckats_value'=>50,'category'=>'Baking','question_text'=>'Which of the following is NOT a type of pastry?','answer_a'=>'Choux','answer_b'=>'Filo','answer_c'=>'Polenta','answer_d'=>'Puff','correct_answer'=>'C','answer_text'=>'Polenta is a dish made from boiled cornmeal, not a pastry.'),
            array('duckats_value'=>50,'category'=>'Baking','question_text'=>'What type of flour has the highest gluten content?','answer_a'=>'Cake flour','answer_b'=>'All-purpose flour','answer_c'=>'Bread flour','answer_d'=>'Pastry flour','correct_answer'=>'C','answer_text'=>'Bread flour has a high protein content (12-14%), which produces more gluten and gives bread its chewy texture.'),
            array('duckats_value'=>50,'category'=>'Baking','question_text'=>'The Maillard reaction in baking produces what?','answer_a'=>'A sour flavour','answer_b'=>'A golden-brown crust and complex flavours','answer_c'=>'A soft, pale surface','answer_d'=>'A sweet glaze','correct_answer'=>'B','answer_text'=>'The Maillard reaction is a chemical reaction between amino acids and sugars that gives baked goods their brown colour and distinctive flavour.'),

            // --- BEER ---
            array('duckats_value'=>30,'category'=>'Beer','question_text'=>'True or false? Ale is brewed using top-fermenting yeast.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Ales use Saccharomyces cerevisiae, which ferments at warmer temperatures and rises to the top of the fermenting vessel.'),
            array('duckats_value'=>30,'category'=>'Beer','question_text'=>'True or false? Lager is brewed using top-fermenting yeast.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Lager uses bottom-fermenting yeast (Saccharomyces pastorianus) and ferments at cooler temperatures.'),
            array('duckats_value'=>30,'category'=>'Beer','question_text'=>'True or false? Hops are used in beer primarily as a sweetener.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Hops provide bitterness to balance the sweetness of malt, and also act as a preservative.'),
            array('duckats_value'=>30,'category'=>'Beer','question_text'=>'True or false? Stout is a type of dark beer.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Stouts are dark beers made using roasted malt or roasted barley, hops, water, and yeast.'),
            array('duckats_value'=>30,'category'=>'Beer','question_text'=>'True or false? Pilsner originated in the Czech Republic.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Pilsner takes its name from the city of Pilsen (Plzeň) in Bohemia, Czech Republic.'),
            array('duckats_value'=>40,'category'=>'Beer','question_text'=>'What grain is most commonly used to make beer?','answer_a'=>'Wheat','answer_b'=>'Barley','answer_c'=>'Rye','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Barley is the most widely used grain in beer production due to its high starch content and enzyme activity.'),
            array('duckats_value'=>40,'category'=>'Beer','question_text'=>'What does IBU stand for in beer?','answer_a'=>'International Brewing Unit','answer_b'=>'International Bitterness Units','answer_c'=>'Ingredient Base Unit','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'IBU measures the bitterness of beer from hops on a scale from 0 to over 100.'),
            array('duckats_value'=>50,'category'=>'Beer','question_text'=>'Which country produces Guinness stout?','answer_a'=>'England','answer_b'=>'Scotland','answer_c'=>'Ireland','answer_d'=>'Germany','correct_answer'=>'C','answer_text'=>'Guinness has been brewed at the St. James\'s Gate Brewery in Dublin, Ireland since 1759.'),
            array('duckats_value'=>50,'category'=>'Beer','question_text'=>'What is the German word for beer purity law?','answer_a'=>'Bierfest','answer_b'=>'Reinheitsgebot','answer_c'=>'Biersteuer','answer_d'=>'Braukunst','correct_answer'=>'B','answer_text'=>'The Reinheitsgebot, enacted in 1516, originally specified that beer could only be made from water, barley, and hops.'),

            // --- BEVERAGES ---
            array('duckats_value'=>30,'category'=>'Beverages','question_text'=>'True or false? Espresso contains more caffeine per ounce than drip coffee.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Espresso is more concentrated, with about 63mg of caffeine per ounce vs about 18mg for drip coffee.'),
            array('duckats_value'=>30,'category'=>'Beverages','question_text'=>'True or false? Green tea and black tea come from different plants.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Both green and black tea come from the same plant, Camellia sinensis. The difference is in how the leaves are processed.'),
            array('duckats_value'=>30,'category'=>'Beverages','question_text'=>'True or false? Club soda and sparkling water are identical.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Club soda contains added minerals such as sodium bicarbonate, while sparkling water is simply carbonated water.'),
            array('duckats_value'=>40,'category'=>'Beverages','question_text'=>'What is the main ingredient in a traditional lemonade?','answer_a'=>'Lime juice','answer_b'=>'Lemon juice','answer_c'=>'Orange juice','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Traditional lemonade is made from lemon juice, water, and sugar.'),
            array('duckats_value'=>40,'category'=>'Beverages','question_text'=>'What beverage is made from fermented apple juice?','answer_a'=>'Perry','answer_b'=>'Mead','answer_c'=>'Cider','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'Cider is an alcoholic beverage made from the fermented juice of apples.'),
            array('duckats_value'=>50,'category'=>'Beverages','question_text'=>'Which country is the largest producer of tea in the world?','answer_a'=>'India','answer_b'=>'Sri Lanka','answer_c'=>'Japan','answer_d'=>'China','correct_answer'=>'D','answer_text'=>'China is the world\'s largest producer and consumer of tea, accounting for about 40% of global production.'),

            // --- COCKTAILS ---
            array('duckats_value'=>30,'category'=>'Cocktails','question_text'=>'True or false? A Martini is traditionally made with gin and vermouth.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'The classic Martini consists of gin and dry vermouth, garnished with an olive or lemon twist.'),
            array('duckats_value'=>30,'category'=>'Cocktails','question_text'=>'True or false? A Mojito is made with bourbon.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'A Mojito is made with white rum, lime juice, sugar, mint, and soda water.'),
            array('duckats_value'=>30,'category'=>'Cocktails','question_text'=>'True or false? Grenadine is made from pomegranate.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Traditional grenadine is made from pomegranate juice, though many commercial versions use artificial flavouring.'),
            array('duckats_value'=>40,'category'=>'Cocktails','question_text'=>'What is the base spirit in a Margarita?','answer_a'=>'Vodka','answer_b'=>'Rum','answer_c'=>'Tequila','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'A Margarita consists of tequila, triple sec or Cointreau, and lime juice.'),
            array('duckats_value'=>40,'category'=>'Cocktails','question_text'=>'What gives a Cosmopolitan its pink colour?','answer_a'=>'Grenadine','answer_b'=>'Cranberry juice','answer_c'=>'Rose water','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'A Cosmopolitan contains vodka, triple sec, cranberry juice, and lime juice.'),
            array('duckats_value'=>50,'category'=>'Cocktails','question_text'=>'Which cocktail is known as the "King of Cocktails"?','answer_a'=>'Manhattan','answer_b'=>'Negroni','answer_c'=>'Old Fashioned','answer_d'=>'Martini','correct_answer'=>'D','answer_text'=>'The Martini is often called the King of Cocktails and is one of the most recognized cocktails in the world.'),
            array('duckats_value'=>50,'category'=>'Cocktails','question_text'=>'What are the three ingredients in a Negroni?','answer_a'=>'Gin, Campari, sweet vermouth','answer_b'=>'Vodka, Campari, dry vermouth','answer_c'=>'Rum, Aperol, sweet vermouth','answer_d'=>'Gin, Aperol, dry vermouth','correct_answer'=>'A','answer_text'=>'A Negroni is made in equal parts: gin, Campari, and sweet vermouth, stirred with ice.'),

            // --- COFFEE/TEA ---
            array('duckats_value'=>30,'category'=>'Coffee/Tea','question_text'=>'True or false? A latte contains more espresso than a cappuccino.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Both typically contain one or two shots of espresso. The difference is the ratio of milk to foam.'),
            array('duckats_value'=>30,'category'=>'Coffee/Tea','question_text'=>'True or false? Oolong tea is partially oxidized.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Oolong tea falls between green tea (unoxidized) and black tea (fully oxidized) in terms of oxidation.'),
            array('duckats_value'=>40,'category'=>'Coffee/Tea','question_text'=>'What coffee drink consists of espresso and steamed milk with a small amount of foam?','answer_a'=>'Cappuccino','answer_b'=>'Macchiato','answer_c'=>'Latte','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'A latte (caffè latte) is made with one or two shots of espresso and steamed milk, topped with a small amount of milk foam.'),
            array('duckats_value'=>40,'category'=>'Coffee/Tea','question_text'=>'What does "chai" mean in Hindi?','answer_a'=>'Spice','answer_b'=>'Tea','answer_c'=>'Milk','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Chai simply means "tea" in Hindi, which is why "chai tea" is technically redundant.'),
            array('duckats_value'=>50,'category'=>'Coffee/Tea','question_text'=>'Which country produces the most coffee in the world?','answer_a'=>'Colombia','answer_b'=>'Vietnam','answer_c'=>'Ethiopia','answer_d'=>'Brazil','correct_answer'=>'D','answer_text'=>'Brazil is the world\'s largest coffee producer, accounting for about one-third of global production.'),

            // --- CONDIMENTS ---
            array('duckats_value'=>30,'category'=>'Condiments','question_text'=>'True or false? Ketchup was originally made with mushrooms, not tomatoes.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'The original ketchup (or catsup) was a British sauce made from mushrooms, walnuts, or oysters. Tomato ketchup became popular in the 19th century.'),
            array('duckats_value'=>30,'category'=>'Condiments','question_text'=>'True or false? Worcestershire sauce contains anchovies.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Worcestershire sauce contains fermented anchovies among its many ingredients.'),
            array('duckats_value'=>30,'category'=>'Condiments','question_text'=>'True or false? Dijon mustard originated in Germany.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Dijon mustard originated in Dijon, France, and has been produced there since the 13th century.'),
            array('duckats_value'=>40,'category'=>'Condiments','question_text'=>'What gives Tabasco sauce its heat?','answer_a'=>'Jalapeño peppers','answer_b'=>'Cayenne peppers','answer_c'=>'Tabasco peppers','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'Tabasco sauce is made from tabasco peppers, vinegar, and salt, and is aged in white oak barrels.'),
            array('duckats_value'=>50,'category'=>'Condiments','question_text'=>'Which country is the largest consumer of mayonnaise per capita?','answer_a'=>'United States','answer_b'=>'France','answer_c'=>'Russia','answer_d'=>'Japan','correct_answer'=>'C','answer_text'=>'Russia consumes more mayonnaise per capita than any other country, using it in many traditional dishes.'),

            // --- CONFECTIONARY ---
            array('duckats_value'=>30,'category'=>'Confectionary','question_text'=>'True or false? White chocolate contains cocoa solids.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'White chocolate contains cocoa butter but no cocoa solids, which is why some argue it is not true chocolate.'),
            array('duckats_value'=>30,'category'=>'Confectionary','question_text'=>'True or false? Marzipan is made primarily from almonds and sugar.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Marzipan is a confection made from ground almonds and sugar, sometimes with egg whites or corn syrup.'),
            array('duckats_value'=>40,'category'=>'Confectionary','question_text'=>'What is the main flavouring in traditional Turkish delight?','answer_a'=>'Rose water','answer_b'=>'Orange blossom','answer_c'=>'Vanilla','answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Traditional Turkish delight (lokum) is flavoured with rose water and dusted with icing sugar.'),
            array('duckats_value'=>50,'category'=>'Confectionary','question_text'=>'What temperature is sugar cooked to for hard candy?','answer_a'=>'270°F (132°C)','answer_b'=>'300–310°F (149–154°C)','answer_c'=>'250°F (121°C)','answer_d'=>'320°F (160°C)','correct_answer'=>'B','answer_text'=>'Hard crack stage, used for hard candies and lollipops, occurs between 300–310°F (149–154°C).'),

            // --- DAIRY ---
            array('duckats_value'=>30,'category'=>'Dairy','question_text'=>'True or false? Mozzarella is traditionally made from buffalo milk.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Traditional mozzarella di bufala is made from the milk of water buffaloes in Italy.'),
            array('duckats_value'=>30,'category'=>'Dairy','question_text'=>'True or false? Brie and Camembert are both soft-ripened cheeses.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Both Brie and Camembert are soft-ripened (bloomy rind) cheeses, treated with mold Penicillium camemberti.'),
            array('duckats_value'=>30,'category'=>'Dairy','question_text'=>'True or false? Gouda is a hard Swiss cheese.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Gouda is a Dutch cheese, originating from the city of Gouda in the Netherlands.'),
            array('duckats_value'=>40,'category'=>'Dairy','question_text'=>'What type of milk is used to make authentic Parmesan (Parmigiano-Reggiano)?','answer_a'=>'Sheep milk','answer_b'=>'Buffalo milk','answer_c'=>'Cow milk','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'Parmigiano-Reggiano is made exclusively from cow\'s milk from cows raised in a specific region of Italy.'),
            array('duckats_value'=>50,'category'=>'Dairy','question_text'=>'Which country produces Roquefort cheese?','answer_a'=>'Italy','answer_b'=>'Spain','answer_c'=>'France','answer_d'=>'Switzerland','correct_answer'=>'C','answer_text'=>'Roquefort is a French blue cheese made from sheep\'s milk, aged in caves in the Roquefort-sur-Soulzon region.'),

            // --- DESSERTS ---
            array('duckats_value'=>30,'category'=>'Desserts','question_text'=>'True or false? Crème brûlée is a French dessert.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Crème brûlée consists of a rich custard base topped with a contrasting layer of hardened caramelized sugar.'),
            array('duckats_value'=>30,'category'=>'Desserts','question_text'=>'True or false? Tiramisu originated in France.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Tiramisu is an Italian dessert. It originated in the Veneto region of northeastern Italy.'),
            array('duckats_value'=>40,'category'=>'Desserts','question_text'=>'What does "tiramisu" mean in Italian?','answer_a'=>'Sweet dream','answer_b'=>'Pick me up','answer_c'=>'Soft cloud','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'"Tiramisu" comes from the Italian phrase "tira mi su" which means "pick me up" or "lift me up."'),
            array('duckats_value'=>40,'category'=>'Desserts','question_text'=>'What is the main ingredient in a soufflé?','answer_a'=>'Whipped cream','answer_b'=>'Egg whites','answer_c'=>'Gelatin','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Soufflés get their characteristic rise from beaten egg whites that expand when heated.'),
            array('duckats_value'=>50,'category'=>'Desserts','question_text'=>'Which dessert is made by layering ladyfingers with mascarpone and espresso?','answer_a'=>'Panna cotta','answer_b'=>'Cannoli','answer_c'=>'Tiramisu','answer_d'=>'Zabaglione','correct_answer'=>'C','answer_text'=>'Tiramisu is made with ladyfingers dipped in espresso, layered with mascarpone cheese and eggs, dusted with cocoa.'),

            // --- FRUIT ---
            array('duckats_value'=>30,'category'=>'Fruit','question_text'=>'True or false? A tomato is botanically a fruit.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Botanically, a tomato is a fruit because it develops from the fertilized ovary of a flower and contains seeds.'),
            array('duckats_value'=>30,'category'=>'Fruit','question_text'=>'True or false? Bananas grow on trees.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Bananas grow on large herbaceous plants, not trees. The trunk is actually a pseudostem made of leaf bases.'),
            array('duckats_value'=>30,'category'=>'Fruit','question_text'=>'True or false? Avocados are a type of berry.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Botanically, avocados are large berries with a single seed.'),
            array('duckats_value'=>40,'category'=>'Fruit','question_text'=>'Which fruit is known as the "king of fruits"?','answer_a'=>'Mango','answer_b'=>'Durian','answer_c'=>'Jackfruit','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Durian is often referred to as the "king of fruits" in Southeast Asia, known for its strong odour.'),
            array('duckats_value'=>50,'category'=>'Fruit','question_text'=>'Which country is the largest producer of mangoes?','answer_a'=>'Brazil','answer_b'=>'Mexico','answer_c'=>'Thailand','answer_d'=>'India','correct_answer'=>'D','answer_text'=>'India produces more than 40% of the world\'s mangoes and is by far the largest producer.'),

            // --- GRAINS ---
            array('duckats_value'=>30,'category'=>'Grains','question_text'=>'True or false? Quinoa is technically a grain.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Quinoa is technically a seed, but it is often referred to as a pseudo-cereal because it is used in similar ways to grains.'),
            array('duckats_value'=>30,'category'=>'Grains','question_text'=>'True or false? Oats are naturally gluten-free.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Oats are naturally gluten-free, but are often contaminated with gluten during processing.'),
            array('duckats_value'=>40,'category'=>'Grains','question_text'=>'What grain is polenta made from?','answer_a'=>'Barley','answer_b'=>'Millet','answer_c'=>'Cornmeal','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'Polenta is made from ground yellow or white cornmeal, boiled in water or stock.'),
            array('duckats_value'=>50,'category'=>'Grains','question_text'=>'Which grain is used to make sushi rice?','answer_a'=>'Jasmine rice','answer_b'=>'Basmati rice','answer_c'=>'Brown rice','answer_d'=>'Short-grain Japanese rice','correct_answer'=>'D','answer_text'=>'Sushi rice is made from short-grain Japanese rice seasoned with rice vinegar, sugar, and salt.'),

            // --- HERBS/SPICES ---
            array('duckats_value'=>30,'category'=>'Herbs/Spices','question_text'=>'True or false? Saffron is the most expensive spice in the world by weight.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Saffron is derived from the stigmas of the Crocus sativus flower and requires enormous labour to harvest.'),
            array('duckats_value'=>30,'category'=>'Herbs/Spices','question_text'=>'True or false? Cinnamon comes from the bark of a tree.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Cinnamon is derived from the inner bark of trees in the genus Cinnamomum.'),
            array('duckats_value'=>40,'category'=>'Herbs/Spices','question_text'=>'Which spice gives turmeric its yellow colour?','answer_a'=>'Curcumin','answer_b'=>'Beta-carotene','answer_c'=>'Anthocyanin','answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Curcumin is the main active ingredient in turmeric and is responsible for its yellow colour.'),
            array('duckats_value'=>50,'category'=>'Herbs/Spices','question_text'=>'Which herb is the key ingredient in pesto sauce?','answer_a'=>'Parsley','answer_b'=>'Oregano','answer_c'=>'Basil','answer_d'=>'Thyme','correct_answer'=>'C','answer_text'=>'Traditional Genovese pesto is made from basil, pine nuts, Parmesan, garlic, and olive oil.'),

            // --- HISTORY ---
            array('duckats_value'=>30,'category'=>'History','question_text'=>'True or false? The fork was used in Ancient Rome.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Romans ate with their fingers and used spoons and knives. The fork was introduced to Europe from the Middle East in the 11th century.'),
            array('duckats_value'=>30,'category'=>'History','question_text'=>'True or false? The first restaurant in the world opened in France.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'The first modern restaurant is generally considered to be "Boulanger" which opened in Paris in 1765.'),
            array('duckats_value'=>40,'category'=>'History','question_text'=>'Which ancient civilization first cultivated wine?','answer_a'=>'Romans','answer_b'=>'Greeks','answer_c'=>'Georgians','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'Archaeological evidence suggests that wine production began in the South Caucasus (modern-day Georgia) around 8,000 years ago.'),
            array('duckats_value'=>50,'category'=>'History','question_text'=>'In which decade was the first McDonald\'s restaurant opened?','answer_a'=>'1930s','answer_b'=>'1940s','answer_c'=>'1950s','answer_d'=>'1960s','correct_answer'=>'B','answer_text'=>'The first McDonald\'s restaurant was opened by brothers Richard and Maurice McDonald in San Bernardino, California in 1940.'),

            // --- MEAT ---
            array('duckats_value'=>30,'category'=>'Meat','question_text'=>'True or false? Prosciutto is a type of Italian cured ham.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Prosciutto is an Italian dry-cured ham that is thinly sliced and served raw.'),
            array('duckats_value'=>30,'category'=>'Meat','question_text'=>'True or false? A Wagyu steak comes from Japanese cattle.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Wagyu refers to several breeds of Japanese cattle genetically predisposed to intense marbling.'),
            array('duckats_value'=>40,'category'=>'Meat','question_text'=>'What cut of beef is used to make a traditional Wiener Schnitzel?','answer_a'=>'Sirloin','answer_b'=>'Veal cutlet','answer_c'=>'Pork loin','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Authentic Wiener Schnitzel must be made from veal. Using pork requires it to be called "Schnitzel Wiener Art."'),
            array('duckats_value'=>50,'category'=>'Meat','question_text'=>'Which country is known for producing Ibérico ham?','answer_a'=>'Italy','answer_b'=>'Portugal','answer_c'=>'Spain','answer_d'=>'France','correct_answer'=>'C','answer_text'=>'Jamón Ibérico comes from the Iberian pig raised in Spain (and some parts of Portugal).'),

            // --- MISC ---
            array('duckats_value'=>30,'category'=>'Misc.','question_text'=>'True or false? The word "restaurant" comes from the French word meaning "to restore."','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'The word restaurant derives from the French "restaurer" meaning "to restore." Early restaurants served restorative broths.'),
            array('duckats_value'=>30,'category'=>'Misc.','question_text'=>'True or false? A sommelier specializes in wine service.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'A sommelier is a trained wine professional who specializes in all aspects of wine service.'),
            array('duckats_value'=>40,'category'=>'Misc.','question_text'=>'What does "à la carte" mean?','answer_a'=>'A fixed-price meal','answer_b'=>'Ordering individual dishes from a menu','answer_c'=>'A chef\'s special tasting menu','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'"À la carte" is French for "according to the menu," meaning each dish is ordered and priced separately.'),
            array('duckats_value'=>50,'category'=>'Misc.','question_text'=>'What does the culinary term "mise en place" mean?','answer_a'=>'Put in the oven','answer_b'=>'Everything in its place','answer_c'=>'Reduce the sauce','answer_d'=>'Season to taste','correct_answer'=>'B','answer_text'=>'"Mise en place" is French for "everything in its place," referring to having all ingredients prepared before cooking begins.'),

            // --- NUTS ---
            array('duckats_value'=>30,'category'=>'Nuts','question_text'=>'True or false? Peanuts are technically legumes, not nuts.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Peanuts grow underground and are legumes, belonging to the same family as lentils, peas, and beans.'),
            array('duckats_value'=>30,'category'=>'Nuts','question_text'=>'True or false? Cashews grow inside a hard shell like walnuts.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Cashews grow at the bottom of the cashew apple, exposed to air. The shell contains a caustic resin and must be carefully removed.'),
            array('duckats_value'=>40,'category'=>'Nuts','question_text'=>'Which nut is used to make marzipan?','answer_a'=>'Walnut','answer_b'=>'Hazelnut','answer_c'=>'Almond','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'Marzipan is made from ground almonds (or almond flour) mixed with sugar.'),
            array('duckats_value'=>50,'category'=>'Nuts','question_text'=>'Which is the most expensive nut in the world?','answer_a'=>'Pistachio','answer_b'=>'Pecan','answer_c'=>'Macadamia','answer_d'=>'Pine nut','correct_answer'=>'C','answer_text'=>'Macadamia nuts are the world\'s most expensive nuts due to the difficulty of cracking their extremely hard shells and the slow-growing trees.'),

            // --- OILS ---
            array('duckats_value'=>30,'category'=>'Oils','question_text'=>'True or false? Extra virgin olive oil has a higher smoke point than refined olive oil.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Extra virgin olive oil has a lower smoke point (~375°F/191°C) than refined olive oil (~465°F/240°C).'),
            array('duckats_value'=>40,'category'=>'Oils','question_text'=>'Which oil has the highest smoke point, making it ideal for high-heat cooking?','answer_a'=>'Coconut oil','answer_b'=>'Butter','answer_c'=>'Avocado oil','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'Refined avocado oil has a very high smoke point of around 520°F (270°C), making it excellent for high-heat cooking.'),
            array('duckats_value'=>50,'category'=>'Oils','question_text'=>'What country produces the most olive oil in the world?','answer_a'=>'Italy','answer_b'=>'Greece','answer_c'=>'Spain','answer_d'=>'Turkey','correct_answer'=>'C','answer_text'=>'Spain is the world\'s largest producer of olive oil, accounting for about 44% of global production.'),

            // --- PASTA ---
            array('duckats_value'=>30,'category'=>'Pasta','question_text'=>'True or false? Spaghetti carbonara traditionally contains cream.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Authentic spaghetti alla carbonara contains eggs, guanciale (cured pork cheek), Pecorino Romano, and black pepper. No cream.'),
            array('duckats_value'=>30,'category'=>'Pasta','question_text'=>'True or false? Rigatoni is a type of stuffed pasta.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Rigatoni is a tube-shaped pasta with ridges. Stuffed pastas include ravioli, tortellini, and cannelloni.'),
            array('duckats_value'=>40,'category'=>'Pasta','question_text'=>'What does "al dente" mean when cooking pasta?','answer_a'=>'Fully soft and tender','answer_b'=>'Firm to the bite','answer_c'=>'Slightly undercooked','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'"Al dente" is Italian for "to the tooth," meaning pasta cooked so it is still slightly firm when bitten.'),
            array('duckats_value'=>50,'category'=>'Pasta','question_text'=>'Which pasta shape is traditionally served with pesto?','answer_a'=>'Penne','answer_b'=>'Trofie','answer_c'=>'Rigatoni','answer_d'=>'Farfalle','correct_answer'=>'B','answer_text'=>'Trofie, a short twisted pasta from Liguria, is the traditional shape served with Genovese pesto.'),

            // --- PROCESSED FOODS ---
            array('duckats_value'=>30,'category'=>'Processed Foods','question_text'=>'True or false? Spam is a brand of canned cooked pork.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Spam is a brand of canned cooked pork introduced by Hormel Foods in 1937.'),
            array('duckats_value'=>40,'category'=>'Processed Foods','question_text'=>'What country consumes the most instant noodles per capita?','answer_a'=>'Japan','answer_b'=>'China','answer_c'=>'South Korea','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'South Korea consumes the most instant noodles per capita in the world.'),
            array('duckats_value'=>50,'category'=>'Processed Foods','question_text'=>'Which preservative is commonly used in hot dogs to maintain their pink color?','answer_a'=>'Sodium chloride','answer_b'=>'Sodium nitrate','answer_c'=>'Potassium sorbate','answer_d'=>'Citric acid','correct_answer'=>'B','answer_text'=>'Sodium nitrate (and nitrite) are used in cured meats like hot dogs to prevent bacterial growth and maintain color.'),

            // --- RECIPES ---
            array('duckats_value'=>30,'category'=>'Recipes','question_text'=>'True or false? A roux is made from equal parts flour and fat.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'A roux is a mixture of equal parts (by weight) flour and fat, cooked together and used to thicken sauces.'),
            array('duckats_value'=>40,'category'=>'Recipes','question_text'=>'What are the five French mother sauces?','answer_a'=>'Béchamel, Velouté, Espagnole, Hollandaise, Tomato','answer_b'=>'Béchamel, Velouté, Alfredo, Hollandaise, Tomato','answer_c'=>'Béchamel, Marinara, Espagnole, Hollandaise, Tomato','answer_d'=>null,'correct_answer'=>'A','answer_text'=>'The five French mother sauces codified by Escoffier are Béchamel, Velouté, Espagnole, Hollandaise, and Sauce Tomat.'),
            array('duckats_value'=>50,'category'=>'Recipes','question_text'=>'What is the key technique that distinguishes consommé from regular stock?','answer_a'=>'It is made with only vegetables','answer_b'=>'It is clarified using a raft of egg whites and ground meat','answer_c'=>'It is reduced for longer','answer_d'=>'It contains gelatin','correct_answer'=>'B','answer_text'=>'Consommé is clarified using a raft of egg whites, ground meat, and vegetables that trap impurities as the liquid heats.'),

            // --- SAUCE ---
            array('duckats_value'=>30,'category'=>'Sauce','question_text'=>'True or false? Hollandaise sauce is an emulsion of egg yolks and butter.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Hollandaise is a warm emulsion sauce made from egg yolks, butter, and lemon juice or vinegar.'),
            array('duckats_value'=>40,'category'=>'Sauce','question_text'=>'What is the base of a Béarnaise sauce?','answer_a'=>'Tomato','answer_b'=>'Hollandaise with tarragon and shallots','answer_c'=>'Cream and white wine','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Béarnaise sauce is a derivative of Hollandaise, flavoured with tarragon, chervil, and shallots reduced in white wine and vinegar.'),
            array('duckats_value'=>50,'category'=>'Sauce','question_text'=>'Which sauce is traditionally served with beef Wellington?','answer_a'=>'Béarnaise','answer_b'=>'Bordelaise','answer_c'=>'Demi-glace','answer_d'=>'Madeira sauce','correct_answer'=>'D','answer_text'=>'Beef Wellington is traditionally served with a rich Madeira wine sauce, though demi-glace is also common.'),

            // --- SCIENCE ---
            array('duckats_value'=>30,'category'=>'Science','question_text'=>'True or false? Umami is one of the five basic tastes.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'The five basic tastes are sweet, sour, salty, bitter, and umami. Umami was identified by Japanese chemist Kikunae Ikeda in 1908.'),
            array('duckats_value'=>30,'category'=>'Science','question_text'=>'True or false? Capsaicin, the compound that makes chillies hot, is found mainly in the seeds.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Capsaicin is concentrated in the white pithy membrane (placenta) of the chilli, not the seeds.'),
            array('duckats_value'=>40,'category'=>'Science','question_text'=>'What causes bread to turn brown when toasted?','answer_a'=>'Caramelization','answer_b'=>'The Maillard reaction','answer_c'=>'Oxidation','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'The Maillard reaction between amino acids and reducing sugars causes browning and the development of complex flavours.'),
            array('duckats_value'=>50,'category'=>'Science','question_text'=>'What is the approximate freezing point of pure ethanol?','answer_a'=>'-114°C (-173°F)','answer_b'=>'-80°C (-112°F)','answer_c'=>'-50°C (-58°F)','answer_d'=>'-20°C (-4°F)','correct_answer'=>'A','answer_text'=>'Pure ethanol freezes at -114.1°C (-173.4°F), which is why spirits do not freeze in a standard freezer.'),

            // --- SEAFOOD ---
            array('duckats_value'=>30,'category'=>'Seafood','question_text'=>'True or false? Shrimp and prawns are the same animal.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Shrimp and prawns are different crustaceans from different suborders, though they look similar and the terms are often used interchangeably.'),
            array('duckats_value'=>30,'category'=>'Seafood','question_text'=>'True or false? Oysters can change gender.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Oysters are protandric — they begin life as males and can change to female as they age and grow.'),
            array('duckats_value'=>40,'category'=>'Seafood','question_text'=>'What is the most expensive type of caviar?','answer_a'=>'Beluga','answer_b'=>'Sevruga','answer_c'=>'Osetra','answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Beluga caviar from the beluga sturgeon is the rarest and most expensive, due to the fish\'s long maturation time.'),
            array('duckats_value'=>50,'category'=>'Seafood','question_text'=>'Which country is the world\'s largest consumer of sushi?','answer_a'=>'United States','answer_b'=>'South Korea','answer_c'=>'Japan','answer_d'=>'China','correct_answer'=>'C','answer_text'=>'Japan remains the world\'s largest consumer of sushi, though sushi culture has spread globally.'),

            // --- SERVICE ---
            array('duckats_value'=>30,'category'=>'Service','question_text'=>'True or false? In formal dining, bread plates are placed to the left of the dinner plate.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'In formal Western table settings, the bread plate is placed to the upper left of the dinner plate.'),
            array('duckats_value'=>30,'category'=>'Service','question_text'=>'True or false? When clearing a table, the server should proceed in a clockwise direction.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Standard practice is to clear from the right side of each guest, proceeding counterclockwise around the table.'),
            array('duckats_value'=>40,'category'=>'Service','question_text'=>'What does "deuce" mean in restaurant terminology?','answer_a'=>'A table of four','answer_b'=>'A table of two','answer_c'=>'A double order','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'A "deuce" is restaurant slang for a table set for two guests.'),
            array('duckats_value'=>50,'category'=>'Service','question_text'=>'In French service style, how are dishes typically presented?','answer_a'=>'Pre-plated in the kitchen','answer_b'=>'Served family-style in large bowls','answer_c'=>'Plated tableside from a guéridon trolley','answer_d'=>'Buffet style','correct_answer'=>'C','answer_text'=>'French service involves preparing and plating food tableside using a guéridon (side trolley), offering a theatrical dining experience.'),

            // --- SOUP ---
            array('duckats_value'=>30,'category'=>'Soup','question_text'=>'True or false? Gazpacho is a cold soup.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Gazpacho is a chilled Spanish soup made from raw blended vegetables, typically including tomatoes, peppers, and cucumbers.'),
            array('duckats_value'=>30,'category'=>'Soup','question_text'=>'True or false? French onion soup is traditionally served with Gruyère cheese.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Traditional French onion soup is topped with a crouton and melted Gruyère cheese.'),
            array('duckats_value'=>40,'category'=>'Soup','question_text'=>'What is the main ingredient in vichyssoise?','answer_a'=>'Celery','answer_b'=>'Potato and leek','answer_c'=>'Cauliflower','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Vichyssoise is a thick chilled soup made from puréed leeks, onions, potatoes, cream, and chicken stock.'),
            array('duckats_value'=>50,'category'=>'Soup','question_text'=>'From which country does bouillabaisse originate?','answer_a'=>'Italy','answer_b'=>'Spain','answer_c'=>'France','answer_d'=>'Portugal','correct_answer'=>'C','answer_text'=>'Bouillabaisse is a traditional Provençal fish stew originating from the port city of Marseille, France.'),

            // --- SPIRITS ---
            array('duckats_value'=>30,'category'=>'Spirits','question_text'=>'True or false? Bourbon must be made in Kentucky.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Bourbon can be made anywhere in the United States. It must be made from at least 51% corn and aged in new charred oak barrels.'),
            array('duckats_value'=>30,'category'=>'Spirits','question_text'=>'True or false? Cognac is a type of brandy.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Cognac is a specific type of brandy produced in the Cognac region of France, made from white grapes.'),
            array('duckats_value'=>40,'category'=>'Spirits','question_text'=>'What grain is Scotch whisky primarily made from?','answer_a'=>'Corn','answer_b'=>'Rye','answer_c'=>'Malted barley','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'Single malt Scotch whisky is made from malted barley, water, and yeast, and aged in oak casks for at least 3 years in Scotland.'),
            array('duckats_value'=>50,'category'=>'Spirits','question_text'=>'Which spirit is made from agave plants?','answer_a'=>'Mezcal only','answer_b'=>'Tequila only','answer_c'=>'Both Tequila and Mezcal','answer_d'=>'Rum','correct_answer'=>'C','answer_text'=>'Both tequila and mezcal are made from agave. Tequila is made specifically from blue agave, while mezcal can use many agave varieties.'),

            // --- STATISTICS ---
            array('duckats_value'=>30,'category'=>'Statistics','question_text'=>'True or false? The restaurant industry is one of the largest employers in North America.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'The restaurant and foodservice industry is one of the largest employers in both Canada and the United States.'),
            array('duckats_value'=>40,'category'=>'Statistics','question_text'=>'Approximately what fraction of restaurants fail within their first year?','answer_a'=>'One in ten','answer_b'=>'One in five','answer_c'=>'One in three','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Research suggests approximately 17-20% of restaurants fail within their first year of operation.'),

            // --- TECHNIQUES ---
            array('duckats_value'=>30,'category'=>'Techniques','question_text'=>'True or false? Blanching involves briefly boiling food and then plunging it into ice water.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Blanching stops the cooking process, preserves colour, and can remove bitterness from vegetables.'),
            array('duckats_value'=>30,'category'=>'Techniques','question_text'=>'True or false? Julienne is a knife cut that produces thin, round slices.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'A julienne cut produces thin, uniform matchstick-shaped strips, not round slices.'),
            array('duckats_value'=>40,'category'=>'Techniques','question_text'=>'What cooking method uses water heated to just below boiling?','answer_a'=>'Simmering','answer_b'=>'Poaching','answer_c'=>'Steaming','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Poaching uses liquid heated to 71–82°C (160–180°F) — below simmering — and is used for delicate foods like eggs and fish.'),
            array('duckats_value'=>50,'category'=>'Techniques','question_text'=>'What does "sauté" literally mean in French?','answer_a'=>'To stir','answer_b'=>'To jumped','answer_c'=>'To sear','answer_d'=>'To brown','correct_answer'=>'B','answer_text'=>'"Sauté" comes from the French verb "sauter," meaning "to jump," referring to the tossing of food in a hot pan.'),

            // --- TERMS ---
            array('duckats_value'=>30,'category'=>'Terms','question_text'=>'True or false? "Chiffonade" is a technique for cutting leafy herbs into thin ribbons.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Chiffonade involves stacking leaves, rolling them tightly, then cutting across the roll to produce thin ribbons.'),
            array('duckats_value'=>40,'category'=>'Terms','question_text'=>'What does the culinary term "brunoise" describe?','answer_a'=>'A coarse chop','answer_b'=>'Very small uniform dice','answer_c'=>'A fine mince','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Brunoise is a culinary knife cut where food is finely diced into 1-3mm uniform cubes.'),
            array('duckats_value'=>50,'category'=>'Terms','question_text'=>'What does "en papillote" mean?','answer_a'=>'On a skewer','answer_b'=>'In a sauce','answer_c'=>'Cooked in parchment paper','answer_d'=>'Finished under the broiler','correct_answer'=>'C','answer_text'=>'"En papillote" is a French cooking method where food is enclosed in a folded pouch of parchment and baked, steaming in its own juices.'),

            // --- TRIVIA ---
            array('duckats_value'=>30,'category'=>'Trivia','question_text'=>'True or false? Honey never spoils.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Archaeologists have found 3,000-year-old honey in Egyptian tombs that was still edible. Honey\'s low moisture and acidic pH prevent bacterial growth.'),
            array('duckats_value'=>30,'category'=>'Trivia','question_text'=>'True or false? Chocolate was once used as currency.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Cacao beans were used as currency by the Aztecs and other Mesoamerican civilizations.'),
            array('duckats_value'=>40,'category'=>'Trivia','question_text'=>'Which country invented the croissant?','answer_a'=>'France','answer_b'=>'Austria','answer_c'=>'Switzerland','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'The croissant is based on the Austrian kipferl. It was adapted by Viennese bakers and later popularized in France.'),
            array('duckats_value'=>50,'category'=>'Trivia','question_text'=>'What is the world\'s most consumed fish?','answer_a'=>'Tuna','answer_b'=>'Salmon','answer_c'=>'Herring','answer_d'=>'Anchovies','correct_answer'=>'A','answer_text'=>'Tuna is the world\'s most widely consumed fish, with millions of tonnes consumed annually worldwide.'),

            // --- UTENSILS ---
            array('duckats_value'=>30,'category'=>'Utensils','question_text'=>'True or false? A mandoline is used for slicing food very thinly.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'A mandoline is a slicing tool used to slice and julienne vegetables and other foods with precise, uniform thickness.'),
            array('duckats_value'=>40,'category'=>'Utensils','question_text'=>'What is a spider (or spider strainer) used for in the kitchen?','answer_a'=>'Straining pasta','answer_b'=>'Skimming stock','answer_c'=>'Lifting food from hot oil or water','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'A spider is a wide, shallow wire-mesh basket used to scoop foods out of boiling water or hot oil.'),
            array('duckats_value'=>50,'category'=>'Utensils','question_text'=>'What is a bain-marie used for?','answer_a'=>'Steaming vegetables','answer_b'=>'Gentle, indirect heating to melt or keep food warm','answer_c'=>'Deep frying','answer_d'=>'Smoking food','correct_answer'=>'B','answer_text'=>'A bain-marie (water bath) uses hot water surrounding a container to provide gentle, even heat — used for chocolate, custards, and sauces.'),

            // --- VEGETABLES ---
            array('duckats_value'=>30,'category'=>'Vegetables','question_text'=>'True or false? Artichokes are actually a type of thistle.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'The artichoke is a variety of thistle cultivated as a food. We eat the flower bud before it opens.'),
            array('duckats_value'=>30,'category'=>'Vegetables','question_text'=>'True or false? Potatoes are native to North America.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Potatoes are native to South America, specifically the Andes region of Peru and Bolivia.'),
            array('duckats_value'=>40,'category'=>'Vegetables','question_text'=>'Which vegetable has the highest water content?','answer_a'=>'Celery','answer_b'=>'Cucumber','answer_c'=>'Lettuce','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Cucumbers are about 96% water, one of the highest water contents of any food.'),
            array('duckats_value'=>50,'category'=>'Vegetables','question_text'=>'What gives red cabbage its purple-red color?','answer_a'=>'Carotenoids','answer_b'=>'Anthocyanins','answer_c'=>'Chlorophyll','answer_d'=>'Lycopene','correct_answer'=>'B','answer_text'=>'Anthocyanins are water-soluble pigments responsible for the red, purple, and blue colours of many fruits and vegetables.'),

            // --- WINE ---
            array('duckats_value'=>30,'category'=>'Wine','question_text'=>'True or false? Champagne can only be produced in the Champagne region of France.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'Under EU law, only sparkling wine produced in the Champagne region of France using the méthode champenoise can be called Champagne.'),
            array('duckats_value'=>30,'category'=>'Wine','question_text'=>'True or false? Rosé wine is made by blending red and white wine.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Most rosé is made by leaving red grape skins in contact with the juice for a short time. Blending is rarely used (except in Champagne rosé).'),
            array('duckats_value'=>40,'category'=>'Wine','question_text'=>'Which grape variety is Burgundy red wine made from?','answer_a'=>'Merlot','answer_b'=>'Cabernet Sauvignon','answer_c'=>'Pinot Noir','answer_d'=>null,'correct_answer'=>'C','answer_text'=>'Red Burgundy (Bourgogne Rouge) is made exclusively from Pinot Noir grapes.'),
            array('duckats_value'=>40,'category'=>'Wine','question_text'=>'What does the term "tannin" refer to in wine?','answer_a'=>'The sweetness level','answer_b'=>'A compound causing a drying sensation in the mouth','answer_c'=>'The alcohol content','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Tannins are polyphenolic compounds found in grape skins, seeds, and stems that create a drying, astringent sensation.'),
            array('duckats_value'=>50,'category'=>'Wine','question_text'=>'What is the term for the practice of pouring wine into a wide vessel to aerate it?','answer_a'=>'Degassing','answer_b'=>'Racking','answer_c'=>'Decanting','answer_d'=>'Fining','correct_answer'=>'C','answer_text'=>'Decanting exposes wine to oxygen, softening tannins and releasing aromas. It also separates older wines from sediment.'),

            // --- ADDITIVES ---
            array('duckats_value'=>30,'category'=>'Additives','question_text'=>'True or false? MSG (monosodium glutamate) is derived from natural sources.','answer_a'=>'True','answer_b'=>'False','answer_c'=>null,'answer_d'=>null,'correct_answer'=>'A','answer_text'=>'MSG is commercially produced by fermenting starch, sugar beets, or molasses, similar to how yogurt and vinegar are made.'),
            array('duckats_value'=>40,'category'=>'Additives','question_text'=>'What is the purpose of xanthan gum in food?','answer_a'=>'Sweetener','answer_b'=>'Thickener and stabilizer','answer_c'=>'Preservative','answer_d'=>null,'correct_answer'=>'B','answer_text'=>'Xanthan gum is a polysaccharide used as a food thickener and stabilizer, commonly used in gluten-free baking.'),
            array('duckats_value'=>50,'category'=>'Additives','question_text'=>'What is the E number for aspartame, the artificial sweetener?','answer_a'=>'E110','answer_b'=>'E951','answer_c'=>'E621','answer_d'=>'E211','correct_answer'=>'B','answer_text'=>'Aspartame is designated E951 in Europe. It is approximately 200 times sweeter than sucrose.'),
        );
    }
}
