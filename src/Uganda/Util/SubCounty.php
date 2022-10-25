<?php

namespace Uganda\Util;

trait SubCounty
{
    use Helpers;
    public function sub_counties($sub_county = null)
    {
        $sub_counties = $this->fetch('sub_counties.json');
        if ($sub_county) {
            $filtered = array_filter($sub_counties, $this->filter('name', $sub_county));
            if (empty($filtered)) {
                $this->_error['sub_county'] = 'Sub county ' . $sub_county . ' does not exist.';
            } else {
                $this->_sub_county = [...$filtered][0]['id'];
            }
        } else {
            if (isset($this->_district) && isset($this->_county)) {
                $filtered = array_filter($sub_counties, $this->filter('county', $this->_county));
                $this->_sub_counties['sub_counties'] = $this->format([...$filtered]);
            } else {
                $this->_sub_counties['sub_counties'] = $this->format($sub_counties);
            }
        }

        return $this;
    }
}
