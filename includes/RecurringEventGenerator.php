<?php

namespace BmltEnabled\Mayo;

class RecurringEventGenerator {
    /**
     * Generate recurring events based on a pattern
     * 
     * @param string $startDate The start date in Y-m-d format
     * @param array $pattern The recurrence pattern
     * @param string|null $endDate Optional end date in Y-m-d format
     * @param int $maxEvents Maximum number of events to generate
     * @return array Array of event dates in Y-m-d format
     */
    public function generateEvents($startDate, $pattern, $endDate = null, $maxEvents = 1000) {
        $dates = [];
        $start = new \DateTime($startDate);
        $end = $endDate ? new \DateTime($endDate) : null;
        
        if ($pattern['type'] === 'monthly') {
            $interval = $pattern['interval'];
            
            if (isset($pattern['day'])) {
                // Specific date of month
                $day = (int)$pattern['day'];
                $current = clone $start;
                $current->setDate($current->format('Y'), $current->format('m'), $day);
                
                // If the current date is before the start date, move to next month
                if ($current < $start) {
                    $current->modify('first day of next month');
                    $current->setDate($current->format('Y'), $current->format('m'), $day);
                }
                
                while (($end === null || $current <= $end) && count($dates) < $maxEvents) {
                    $dates[] = $current->format('Y-m-d');
                    $current->modify('first day of +' . $interval . ' month');
                    $current->setDate($current->format('Y'), $current->format('m'), $day);
                }
            } else {
                // Specific weekday (e.g., 1st Sunday)
                list($week, $weekday) = explode(',', $pattern['monthlyWeekday']);
                $week = (int)$week;
                $weekday = (int)$weekday;
                
                $current = clone $start;
                $current->modify('first day of this month');
                
                // Find the first occurrence after the start date
                $target = clone $current;
                if ($week > 0) {
                    if ($week === 1) {
                        $first_day_of_week = (int)$target->format('w');
                        $days_to_add = ($weekday - $first_day_of_week + 7) % 7;
                        $target->modify('+' . $days_to_add . ' days');
                    } else {
                        $target->modify('+' . ($week - 1) . ' weeks');
                        $target->modify('next ' . $this->getWeekdayName($weekday));
                    }
                } else {
                    $target->modify('last ' . $this->getWeekdayName($weekday) . ' of this month');
                }
                
                // If the target is before the start date, move to next month
                if ($target < $start) {
                    $current->modify('first day of next month');
                    $target = clone $current;
                    if ($week > 0) {
                        if ($week === 1) {
                            $first_day_of_week = (int)$target->format('w');
                            $days_to_add = ($weekday - $first_day_of_week + 7) % 7;
                            $target->modify('+' . $days_to_add . ' days');
                        } else {
                            $target->modify('+' . ($week - 1) . ' weeks');
                            $target->modify('next ' . $this->getWeekdayName($weekday));
                        }
                    } else {
                        $target->modify('last ' . $this->getWeekdayName($weekday) . ' of this month');
                    }
                }
                
                while (($end === null || $target <= $end) && count($dates) < $maxEvents) {
                    $dates[] = $target->format('Y-m-d');
                    $current->modify('first day of +' . $interval . ' month');
                    $target = clone $current;
                    if ($week > 0) {
                        if ($week === 1) {
                            $first_day_of_week = (int)$target->format('w');
                            $days_to_add = ($weekday - $first_day_of_week + 7) % 7;
                            $target->modify('+' . $days_to_add . ' days');
                        } else {
                            $target->modify('+' . ($week - 1) . ' weeks');
                            $target->modify('next ' . $this->getWeekdayName($weekday));
                        }
                    } else {
                        $target->modify('last ' . $this->getWeekdayName($weekday) . ' of this month');
                    }
                }
            }
        } elseif ($pattern['type'] === 'weekly') {
            $interval = $pattern['interval'];
            $weekdays = $pattern['days'];
            $current = clone $start;
            
            // Find the first occurrence of any of the specified weekdays
            $current_day = (int)$current->format('w');
            if (!in_array($current_day, $weekdays)) {
                $next_day = min(array_filter($weekdays, function($day) use ($current_day) {
                    return $day > $current_day;
                }));
                if ($next_day === null) {
                    $next_day = min($weekdays);
                    $current->modify('next ' . $this->getWeekdayName($next_day));
                } else {
                    $current->modify('+' . ($next_day - $current_day) . ' days');
                }
            }
            
            // Calculate the last valid date before the end date
            $lastValid = null;
            if ($end !== null) {
                $lastValid = clone $end;
                $lastValid->modify('-1 day');
            }
            
            while (($lastValid === null || $current <= $lastValid) && count($dates) < $maxEvents) {
                $current_day = (int)$current->format('w');
                if (in_array($current_day, $weekdays)) {
                    $dates[] = $current->format('Y-m-d');
                }
                $current->modify('+1 day');
            }
        } elseif ($pattern['type'] === 'daily') {
            $interval = $pattern['interval'];
            $current = clone $start;
            
            while (($end === null || $current < $end) && count($dates) < $maxEvents) {
                $dates[] = $current->format('Y-m-d');
                $current->modify('+' . $interval . ' days');
            }
            
            // Check if the end date itself should be included
            if ($end !== null && $current == $end) {
                $dates[] = $end->format('Y-m-d');
            }
        }
        
        return $dates;
    }
    
    private function getWeekdayName($weekday) {
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $weekdays[$weekday];
    }
} 