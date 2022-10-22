<?php

namespace Uganda\Util;

trait Village {
    use Helpers;
    public function villages() {
        $villages = $this->fetch('villages.json');

        if (isset($this->_district) && isset($this->_county) && isset($this->_sub_county) && isset($this->_parish)) {
            $filtered = array_filter($villages, $this->filter('parish', $this->_parish));
            $this->_villages['villages'] = $this->format([...$filtered]);
        } else {
            $this->_villages['villages'] = $this->format($villages);
        }

        return $this;
    }
}
