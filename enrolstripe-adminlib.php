<?php

class admin_enrol_stripepayment_configtext extends admin_setting_configtext {

	public function write_setting($data) {
        if ($this->name == 'webservice_token' && $data == '') {
            return get_string('token_empty_error', 'enrol_stripepayment');
        }
        if ($this->paramtype === PARAM_INT and $data === '') {
        // do not complain if '' used instead of 0
            $data = 0;
        }
        // $data is a string
        $validated = $this->validate($data);
        if ($validated !== true) {
            return $validated;
        }
        return ($this->config_write($this->name, $data) ? '' : get_string('errorsetting', 'admin'));
    }
}