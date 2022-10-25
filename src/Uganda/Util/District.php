<?php

namespace Uganda\Util;

trait District
{
    use Helpers;
    public function districts($district = null)
    {
        $districts = $this->fetch('districts.json');

        if ($district) {
            $filtered = array_filter($districts, $this->filter('name', $district));

            if (empty($filtered)) {
                $this->_error['district'] = 'District ' . $district . ' does not exist.';
            } else {
                $this->_district = [...$filtered][0]['id'];
            }
        } else {
            $this->_districts['districts'] = $this->format($districts);
        }
        return $this;
    }
}
