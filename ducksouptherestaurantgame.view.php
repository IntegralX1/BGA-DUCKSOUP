<?php
/**
 * ducksouptherestaurantgame.view.php
 *
 * Duck Soup — The Restaurant Game
 *
 * NOTE: Game HTML is now generated entirely in ducksouptherestaurantgame.js
 * via this.bga.gameArea.getElement().insertAdjacentHTML() in the setup() method.
 * This file exists only to satisfy the BGA framework's view requirement.
 * It intentionally contains no game layout or player-specific data.
 */

require_once(APP_BASE_PATH . 'view/common/game.view.php');

class ducksouptherestaurantgame_view_ducksouptherestaurantgame extends game_view
{
    public function getGameName()
    {
        return 'ducksouptherestaurantgame';
    }

    public function build_page($viewArgs)
    {
        // All game HTML is injected from JS setup().
        // No template variables to assign here.
    }
}
