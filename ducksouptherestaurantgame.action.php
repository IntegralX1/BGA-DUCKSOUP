<?php
/**
 * ducksouptherestaurantgame.action.php
 *
 * Duck Soup — The Restaurant Game
 * AJAX action bridge — Phase 3
 *
 * All player actions callable from the front-end via
 * this.bga.actions.performAction('actionName', { args }).
 *
 * Phase 3 additions:
 *   - buySouperDuckat
 *   - cashSouperDuckat
 *   - useSouperDuckats
 *   - skipSouperDuckats
 *   - hireStaff     (updated: now accepts staff_type + staff_value)
 *   - passHire      (new)
 *   - rollForCard   (new)
 *   - returnStaffForPayment (carried from Phase 2)
 */

class action_ducksouptherestaurantgame extends APP_GameAction
{
    public function __default()
    {
        if (self::isArg('notifwindow')) {
            $this->view = 'common_notifwindow';
            $this->viewArgs['table'] = self::getArg('table', AT_posint, true);
        } else {
            $this->view = 'ducksouptherestaurantgame_ducksouptherestaurantgame';
            self::trace('Complete game view');
        }
    }

    // ------------------------------------------------------------------
    // QUESTION PHASE
    // ------------------------------------------------------------------

    public function chooseLetter()
    {
        self::setAjaxMode();
        $letter = self::getArg('letter', AT_alphanum, true);
        $this->game->chooseLetter($letter);
        self::ajaxResponse();
    }

    public function submitAnswer()
    {
        self::setAjaxMode();
        $answer = self::getArg('answer', AT_alphanum, true);
        $this->game->submitAnswer($answer);
        self::ajaxResponse();
    }

    // ------------------------------------------------------------------
    // STAFF DIE & MOVEMENT
    // ------------------------------------------------------------------

    public function rollStaffDie()
    {
        self::setAjaxMode();
        $this->game->rollStaffDie();
        self::ajaxResponse();
    }

    public function rollMovement()
    {
        self::setAjaxMode();
        $this->game->rollMovement();
        self::ajaxResponse();
    }

    // ------------------------------------------------------------------
    // SOUPER DUCKATS
    // ------------------------------------------------------------------

    public function buySouperDuckat()
    {
        self::setAjaxMode();
        $this->game->buySouperDuckat();
        self::ajaxResponse();
    }

    public function cashSouperDuckat()
    {
        self::setAjaxMode();
        $this->game->cashSouperDuckat();
        self::ajaxResponse();
    }

    public function useSouperDuckats()
    {
        self::setAjaxMode();
        $quantity = self::getArg('quantity', AT_posint, true);
        $this->game->useSouperDuckats($quantity);
        self::ajaxResponse();
    }

    public function skipSouperDuckats()
    {
        self::setAjaxMode();
        $this->game->skipSouperDuckats();
        self::ajaxResponse();
    }

    // ------------------------------------------------------------------
    // RESTAURANT CARDS
    // ------------------------------------------------------------------

    public function rollForCard()
    {
        self::setAjaxMode();
        $this->game->rollForCard();
        self::ajaxResponse();
    }

    public function returnStaffForPayment()
    {
        self::setAjaxMode();
        $staffType = self::getArg('staff_type', AT_alphanum_underscore, true);
        $this->game->returnStaffForPayment($staffType);
        self::ajaxResponse();
    }

    // ------------------------------------------------------------------
    // HIRE STAFF
    // ------------------------------------------------------------------

    public function hireStaff()
    {
        self::setAjaxMode();
        $staffType  = self::getArg('staff_type',  AT_alphanum_underscore, true);
        $staffValue = self::getArg('staff_value',  AT_posint,             true);
        $this->game->hireStaff($staffType, $staffValue);
        self::ajaxResponse();
    }

    public function passHire()
    {
        self::setAjaxMode();
        $this->game->passHire();
        self::ajaxResponse();
    }

    // ------------------------------------------------------------------
    // AUCTIONS
    // ------------------------------------------------------------------

    public function placeBid()
    {
        self::setAjaxMode();
        $amount = self::getArg('amount', AT_posint, true);
        $this->game->placeBid($amount);
        self::ajaxResponse();
    }

    public function passBid()
    {
        self::setAjaxMode();
        $this->game->passBid();
        self::ajaxResponse();
    }
}
