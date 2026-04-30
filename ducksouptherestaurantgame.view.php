<?php
/**
 * ducksouptherestaurantgame.view.php
 *
 * Duck Soup — The Restaurant Game
 *
 * All game HTML is generated in ducksouptherestaurantgame.js via
 * this.bga.gameArea.getElement().insertAdjacentHTML() in setup().
 * This file is a minimal stub required by the BGA framework.
 */

require_once(APP_BASE_PATH . 'view/common/game.view.php');

class view_ducksouptherestaurantgame_ducksouptherestaurantgame extends game_view
{
    public function getGameName()
    {
        return 'ducksouptherestaurantgame';
    }

    public function build_page($viewArgs)
    {
        // All game HTML is injected from JS setup() via bga.gameArea.getElement().
        // The TPL contains only empty block markers required by the BGA template engine.
        // No variables or blocks need to be set here.
    }
}

