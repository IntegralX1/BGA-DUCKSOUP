<?php
/**
 * ducksouptherestaurantgame.action.php
 *
 * Duck Soup — The Restaurant Game
 * AJAX action bridge — Phase 3
 *
 * BGA new framework (Bga\GameFramework\Table) requires all action
 * methods to be prefixed with 'act' for auto-wiring.
 * JS calls performAction('chooseLetter') → PHP actChooseLetter().
 *
 * All player actions callable from the front-end via:
 *   this.bga.actions.performAction('actionName', { args })
 * where 'actionName' is the method name WITHOUT the 'act' prefix.
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

    public function actChooseLetter()
    {
        self::setAjaxMode();
        $letter = self::getArg('letter', AT_alphanum, true);
        $this->game->chooseLetter($letter);
        self::ajaxResponse();
    }

    public function actSubmitAnswer()
    {
        self::setAjaxMode();
        $answer = self::getArg('answer', AT_alphanum, true);
        $this->game->submitAnswer($answer);
        self::ajaxResponse();
    }

    // ------------------------------------------------------------------
    // STAFF DIE & MOVEMENT
    // ------------------------------------------------------------------

    public function actRollStaffDie()
    {
        self::setAjaxMode();
        $this->game->rollStaffDie();
        self::ajaxResponse();
    }

    public function actRollMovement()
    {
        self::setAjaxMode();
        $this->game->rollMovement();
        self::ajaxResponse();
    }

    // ------------------------------------------------------------------
    // SOUPER DUCKATS
    // ------------------------------------------------------------------

    public function actBuySouperDuckat()
    {
        self::setAjaxMode();
        $this->game->buySouperDuckat();
        self::ajaxResponse();
    }

    public function actCashSouperDuckat()
    {
        self::setAjaxMode();
        $this->game->cashSouperDuckat();
        self::ajaxResponse();
    }

    public function actUseSouperDuckats()
    {
        self::setAjaxMode();
        $quantity = self::getArg('quantity', AT_posint, true);
        $this->game->useSouperDuckats($quantity);
        self::ajaxResponse();
    }

    public function actSkipSouperDuckats()
    {
        self::setAjaxMode();
        $this->game->skipSouperDuckats();
        self::ajaxResponse();
    }

    // ------------------------------------------------------------------
    // RESTAURANT CARDS
    // ------------------------------------------------------------------

    public function actRollForCard()
    {
        self::setAjaxMode();
        $this->game->rollForCard();
        self::ajaxResponse();
    }

    public function actReturnStaffForPayment()
    {
        self::setAjaxMode();
        $staffType = self::getArg('staff_type', AT_alphanum_underscore, true);
        $this->game->returnStaffForPayment($staffType);
        self::ajaxResponse();
    }

    // ------------------------------------------------------------------
    // HIRE STAFF
    // ------------------------------------------------------------------

    public function actHireStaff()
    {
        self::setAjaxMode();
        $staffType  = self::getArg('staff_type',  AT_alphanum_underscore, true);
        $staffValue = self::getArg('staff_value',  AT_posint,             true);
        $this->game->hireStaff($staffType, $staffValue);
        self::ajaxResponse();
    }

    public function actPassHire()
    {
        self::setAjaxMode();
        $this->game->passHire();
        self::ajaxResponse();
    }

    // ------------------------------------------------------------------
    // AUCTIONS
    // ------------------------------------------------------------------

    public function actPlaceBid()
    {
        self::setAjaxMode();
        $amount = self::getArg('amount', AT_posint, true);
        $this->game->placeBid($amount);
        self::ajaxResponse();
    }

    public function actPassBid()
    {
        self::setAjaxMode();
        $this->game->passBid();
        self::ajaxResponse();
    }
}
