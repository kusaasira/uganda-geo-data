<?php

namespace Uganda\Util;

trait County
{
    use Helpers;
    public function counties($county = null)
    {
        $counties = $this->fetch('counties.json');
        if ($county) {
            $filtered = array_filter($counties, $this->filter('name', $county));
            if (empty($filtered)) {
                $this->_error['county'] = 'County ' . $county . ' does not exist.';
            } else {
                $this->_county = [...$filtered][0]['id'];
            }
        } else {
            if (isset($this->_district)) {
                $filtered = array_filter($counties, $this->filter('district', $this->_district));
                $this->_counties['counties'] = $this->format([...$filtered]);
            } else {
                $this->_counties['counties'] = $this->format($counties);
            }
        }

        return $this;
    }
}
