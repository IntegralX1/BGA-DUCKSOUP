<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * DuckSoupTheRestaurantGame implementation : @ RJ Hidson <rhidson1@nait.ca>, @ Ashton Williams <ashtonw@nait.ca>, @ Rubelyn Ragasa <rragasa1@nait.ca>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * ducksouptherestaurantgame.action.php
 *
 * DuckSoupTheRestaurantGame main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/ducksouptherestaurantgame/ducksouptherestaurantgame/myAction.html", ...)
 *
 * ducksouptherestaurantgame.action.php
 *
 * Duck Soup — The Restaurant Game
 * BGA implementation: Phase 2
 *
 * AJAX action entry points. Each public method here corresponds to:
 *   - A possibleaction defined in states.inc.php
 *   - A matching player action method in ducksouptherestaurantgame.game.php
 *   - An ajaxcall() in ducksouptherestaurantgame.js
 *
 * Argument types:
 *   AT_alphanum   — letters/numbers only (safe for staff type strings)
 *   AT_posint     — positive integer
 *   AT_bool       — boolean
 *   AT_enum       — one of a defined set of values
 */
 
class action_ducksouptherestaurantgame extends APP_GameAction
{
    // Constructor: please do not modify
    public function __default()
    {
        if (self::isArg('notifwindow')) {
            $this->view = 'common_notifwindow';
            $this->viewArgs['table'] = self::getArg('table', AT_posint, true);
        } else {
            $this->view = 'ducksouptherestaurantgame_ducksouptherestaurantgame';
            self::trace('Complete reinitialization of board game');
        }
    }
 
    // ------------------------------------------------------------------
    // STATE 2: chooseQuestion
    // Active player picks a letter A/B/C/D before their roll.
    // ------------------------------------------------------------------
    public function chooseLetter()
    {
        self::setAjaxMode();
 
        $letter = self::getArg('letter', AT_alphanum, true);
 
        // Validate server-side before forwarding
        if (!in_array(strtoupper($letter), array('A', 'B', 'C', 'D'))) {
            throw new BgaUserException(self::_('Invalid letter. Please choose A, B, C or D.'));
        }
 
        $this->game->chooseLetter(strtoupper($letter));
 
        self::ajaxResponse();
    }
 
    // ------------------------------------------------------------------
    // STATE 3: answerQuestion
    // Active player submits their answer to the trivia question.
    // ------------------------------------------------------------------
    public function submitAnswer()
    {
        self::setAjaxMode();
 
        $answer = self::getArg('answer', AT_alphanum, true);
 
        if (!in_array(strtoupper($answer), array('A', 'B', 'C', 'D'))) {
            throw new BgaUserException(self::_('Invalid answer. Please choose A, B, C or D.'));
        }
 
        $this->game->submitAnswer(strtoupper($answer));
 
        self::ajaxResponse();
    }
 
    // ------------------------------------------------------------------
    // STATE 4: rollStaffDie
    // Active player rolls the 12-sided Staff Die.
    // ------------------------------------------------------------------
    public function rollStaffDie()
    {
        self::setAjaxMode();
 
        $this->game->rollStaffDie();
 
        self::ajaxResponse();
    }
 
    // ------------------------------------------------------------------
    // STATE 5: rollMovement
    // Active player rolls the two movement dice.
    // ------------------------------------------------------------------
    public function rollMovement()
    {
        self::setAjaxMode();
 
        $this->game->rollMovement();
 
        self::ajaxResponse();
    }
 
    // ------------------------------------------------------------------
    // STATE 5: rollMovement (also available in this state)
    // Active player plays one or more Souper Duckats for extra movement.
    // ------------------------------------------------------------------
    public function playSouperDuckat()
    {
        self::setAjaxMode();
 
        // count: how many Souper Duckats to play (min 1)
        $count = self::getArg('count', AT_posint, false, 1);
 
        $this->game->playSouperDuckat((int) $count);
 
        self::ajaxResponse();
    }
 
    // ------------------------------------------------------------------
    // STATE 8: helpWantedBid / hire squares
    // Player hires an Excellent staff member from the Staff Box.
    // ------------------------------------------------------------------
    public function hireStaff()
    {
        self::setAjaxMode();
 
        // staff_type must match one of the 12 defined types in game.php STAFF constant
        $staffType = self::getArg('staff_type', AT_alphanum, true);
 
        $this->game->hireStaff($staffType);
 
        self::ajaxResponse();
    }
 
    // ------------------------------------------------------------------
    // STATE 10: resolveRestaurant
    // Active player resolves the drawn Restaurant card.
    // ------------------------------------------------------------------
    public function resolveRestaurantCard()
    {
        self::setAjaxMode();
 
        $this->game->resolveRestaurantCard();
 
        self::ajaxResponse();
    }
 
    // ------------------------------------------------------------------
    // STATE 10: resolveRestaurant
    // Active player returns an Excellent staff tile to cover a payment.
    // ------------------------------------------------------------------
    public function returnStaffForPayment()
    {
        self::setAjaxMode();
 
        $staffType = self::getArg('staff_type', AT_alphanum, true);
 
        $this->game->returnStaffForPayment($staffType);
 
        self::ajaxResponse();
    }
 
    // ------------------------------------------------------------------
    // STATE 7 & 8: staffQuitsBid / helpWantedBid
    // A bidding player places a bid in the active auction.
    // ------------------------------------------------------------------
    public function placeBid()
    {
        self::setAjaxMode();
 
        $amount = self::getArg('amount', AT_posint, true);
 
        $this->game->placeBid((int) $amount);
 
        self::ajaxResponse();
    }
 
    // ------------------------------------------------------------------
    // STATE 7 & 8: staffQuitsBid / helpWantedBid
    // A bidding player passes on the current auction.
    // ------------------------------------------------------------------
    public function passBid()
    {
        self::setAjaxMode();
 
        $this->game->passBid();
 
        self::ajaxResponse();
    }
}

