<?php

namespace Uganda;

class Geo extends Helpers {

    public function districts($district = null) {
        $districts = parent::fetch('districts.json');
        
        if ($district) {
            $filtered = array_filter($districts, parent::filter('name',$district));
            
            if (empty($filtered)) {
                $this->_error['district'] = 'District ' . $district . ' does not exist.';
            } else {
                $this->_district = [...$filtered][0]['id'];
            }
        } else {
            $this->_districts['districts'] = parent::format($districts);
        }
        return $this;
    }

    public function counties($county = null) {
        $counties = parent::fetch('counties.json');
        if ($county) {
            $filtered = array_filter($counties, parent::filter('name', $county));
            if (empty($filtered)) {
                $this->_error['county'] = 'County ' . $county . ' does not exist.';
            } else {
                $this->_county = [...$filtered][0]['id'];
            }
        } else {
            if (isset($this->_district)) {
                $filtered = array_filter($counties, parent::filter('district', $this->_district));
                $this->_counties['counties'] = parent::format([...$filtered]);
            } else {
                $this->_counties['counties'] = parent::format($counties);
            }
        }

        return $this;
    }

    public function sub_counties($sub_county = null) {
        $sub_counties = parent::fetch('sub_counties.json');
        if ($sub_county) {
            $filtered = array_filter($sub_counties, parent::filter('name', $sub_county));
            if (empty($filtered)) {
                $this->_error['sub_county'] = 'Sub county ' . $sub_county . ' does not exist.';
            }
            else {
                $this->_sub_county = [...$filtered][0]['id'];
            }
        } else {
            if (isset($this->_district) && isset($this->_county)) {
                $filtered = array_filter($sub_counties, parent::filter('county', $this->_county));
                $this->_sub_counties['sub_counties'] = parent::format([...$filtered]);
            } else {
                $this->_sub_counties['sub_counties'] = parent::format($sub_counties);
            }
        }

        return $this;
    }

    public function parishes($parish = null) {
        $parishes = parent::fetch('parishes.json');
        if ($parish) {
            $filtered = array_filter($parishes, parent::filter('name', $parish));
            if (empty($filtered)) {
                $this->_error['parish'] = 'Parish ' . $parish . ' does not exist.';
            } else {
                $this->_parish = [...$filtered][0]['id'];
            }
        } else {
            if (isset($this->_district) && isset($this->_county) && isset($this->_sub_county)) {
                
                $filtered = array_filter($parishes, parent::filter('subcounty', $this->_sub_county));
                $this->_parishes['parishes'] = parent::format([...$filtered]);
            } else {
                $this->_parishes['parishes'] = parent::format($parishes);
            }
        }

        return $this;
    }

    public function villages() {
        $villages = parent::fetch('villages.json');

        if (isset($this->_district) && isset($this->_county) && isset($this->_sub_county) && isset($this->_parish)) {
            $filtered = array_filter($villages, parent::filter('parish', $this->_parish));
            $this->_villages['villages'] = parent::format([...$filtered]);
        } else {
            $this->_villages['villages'] = parent::format($villages);
        }

        return $this;
    }

    public function all() {
        $properties = get_object_vars($this);
        header("Content-Type:application/json");
        
        if (array_key_exists("_error", $properties)) {
            $error['errors'] = $properties['_error'];
            return json_encode($error);
        }
        return json_encode(end($properties));
    }
}
