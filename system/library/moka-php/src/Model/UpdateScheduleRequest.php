<?php

namespace Moka\Model;

class UpdateScheduleRequest extends Model
{
    /**
     * @var integer
     */
    protected $dealerSaleScheduleId;

    /**
     * @var string
     */
    protected $scheduleName;

    /**
     * @var integer
     */
    protected $dailyWeeklyMonthly;

    /**
     * @var integer
     */
    protected $everyX;

    /**
     * @var string
     */
    protected $daysOfWeek;

    /**
     * @var string
     */
    protected $daysOfMonth;

    /**
     * @return integer
     */
    public function getDealerSaleScheduleId()
    {
        return $this->dealerSaleScheduleId;
    }

    /**
     * @param integer $dealerSaleScheduleId  
     */
    public function setDealerSaleScheduleId($dealerSaleScheduleId)
    {
        $this->dealerSaleScheduleId = $dealerSaleScheduleId;
    }

    /**
     * @return string
     */
    public function getScheduleName()
    {
        return $this->scheduleName;
    }

    /**
     * @param string $scheduleName  
     */
    public function setScheduleName($scheduleName)
    {
        $this->scheduleName = $scheduleName;
    }

    /**
     * @return integer
     */
    public function getDailyWeeklyMonthly()
    {
        return $this->dailyWeeklyMonthly;
    }

    /**
     * @param integer $dailyWeeklyMonthly  
     */
    public function setDailyWeeklyMonthly($dailyWeeklyMonthly)
    {
        $this->dailyWeeklyMonthly = $dailyWeeklyMonthly;
    }

    /**
     * @return integer
     */
    public function getEveryX()
    {
        return $this->everyX;
    }

    /**
     * @param integer $everyX  
     */
    public function setEveryX($everyX)
    {
        $this->everyX = $everyX;
    }

    /**
     * @return string
     */
    public function getDaysOfWeek()
    {
        return $this->daysOfWeek;
    }

    /**
     * @param string $daysOfWeek  
     */
    public function setDaysOfWeek($daysOfWeek)
    {
        $this->daysOfWeek = $daysOfWeek;
    }

    /**
     * @return string
     */
    public function getDaysOfMonth()
    {
        return $this->daysOfMonth;
    }

    /**
     * @param string $daysOfMonth  
     */
    public function setDaysOfMonth($daysOfMonth)
    {
        $this->daysOfMonth = $daysOfMonth;
    }

    public function toArray()
    {
        return [
            'DealerSaleScheduleId' => $this->getDealerSaleScheduleId(),
            'ScheduleName' => $this->getScheduleName(),
            'DailyWeeklyMonthly' => $this->getDailyWeeklyMonthly(),
            'EveryX' => $this->getEveryX(),
            'DaysOfWeek' => $this->getDaysOfWeek(),
            'DaysOfMonth' => $this->getDaysOfMonth()
        ];
    }
}
