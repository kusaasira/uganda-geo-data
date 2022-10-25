<?php

namespace Uganda\Util;

trait Parish
{
    use Helpers;
    public function parishes($parish = null)
    {
        $parishes = $this->fetch('parishes.json');
        if ($parish) {
            $filtered = array_filter($parishes, $this->filter('name', $parish));
            if (empty($filtered)) {
                $this->_error['parish'] = 'Parish ' . $parish . ' does not exist.';
            } else {
                $this->_parish = [...$filtered][0]['id'];
            }
        } else {
            if (isset($this->_district) && isset($this->_county) && isset($this->_sub_county)) {
                $filtered = array_filter($parishes, $this->filter('subcounty', $this->_sub_county));
                $this->_parishes['parishes'] = $this->format([...$filtered]);
            } else {
                $this->_parishes['parishes'] = $this->format($parishes);
            }
        }

        return $this;
    }
}
