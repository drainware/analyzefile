<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

function getFileList($directory, $recursive) {
    $array_items = array();

    if (false != ($handle = opendir($directory))) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                if (is_dir($directory . "/" . $file)) {
                    if ($recursive) {
                        $array_items = array_merge($array_items, getFileList($directory . "/" . $file, $recursive));
                    }
                    $file = $directory . "/" . $file;
                    $array_items[] = preg_replace("/\/\//si", "/", $file);
                } else {
                    $file = $directory . "/" . $file;
                    $array_items[] = preg_replace("/\/\//si", "/", $file);
                }
            }
        }
        closedir($handle);
    }
    return $array_items;
}


function object_to_array($obj) {
        $arr = array();
        $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
        foreach ($_arr as $key => $val) {
                $val = (is_array($val) || is_object($val)) ? object_to_array($val) : $val;
                $arr[$key] = $val;
        }
        return $arr;
}

function getGroupsNamePolicy($groups_policy_config){
    $groups_name = array();
    foreach ($groups_policy_config as $group_policy) {
        $aux_var = explode('_', $group_policy);
        $groups_name[] = sizeof($aux_var) == 1 ? $aux_var[0] : $aux_var[1];
    }
    return $groups_name;
}


function unifyGroupConfig($group_config){
    $group_config_object = object_to_array(json_decode($group_config));
    
    $user_groups = getGroupsNamePolicy($group_config_object['groups_user']);
    
    $value_action = array(null => 0, 'log' => 1, 'alert' => 2, "block" => 3);
    $value_severity = array(null => 0, 'low' => 1, 'medium' => 2, 'high' => 3);
    
    $group_policy = array();
    
    $group_policy['subconcepts'] = array();
    foreach ($group_config_object['subconcepts'] as $subconcept_id => $subconcept_object) {
        $subconcept = array();
        $subconcept['id'] = $subconcept_id;
        $subconcept['subconcept'] = $subconcept_object['subconcept'];
        $subconcept['description'] = $subconcept_object['description'];
        $subconcept['verify'] = $subconcept_object['verify'];
        $subconcept['action'] = 'log';
        $subconcept['severity'] = 'low';
        $subconcept['policies_id'] = $subconcept_object['policies_id'];
        
        foreach ($subconcept['policies_id'] as $policy_id) {
            foreach ($user_groups as $user_group) {
                if($value_action[$group_config_object['policies'][$policy_id][$user_group]['action']] > $value_action[$subconcept['action']]){
                    $subconcept['action'] = $group_config_object['policies'][$policy_id][$user_group]['action'];
                    $subconcept['severity'] = $group_config_object['policies'][$policy_id][$user_group]['severity'];
                } else {
                    if($value_severity[$group_config_object['policies'][$policy_id][$user_group]['severity']] > $value_severity[$subconcept['severity']]){
                        $subconcept['severity'] = $group_config_object['policies'][$policy_id][$user_group]['severity'];
                    }
                }
            }
        }
        
        $group_policy['subconcepts'][] = $subconcept;
    }
    
    $group_policy['rules'] = array();
    foreach ($group_config_object['rules'] as $rule_id => $rule_object) {
        $rule = array();
        $rule['id'] = $rule_id;
        $rule['rule'] = $rule_object['rule'];
        $rule['description'] = $rule_object['description'];
        $rule['verify'] = isset($rule_object['verify']) ? $rule_object['verify'] : null;
        $rule['action'] = 'log';
        $rule['severity'] = 'low';
        $rule['policies_id'] = $rule_object['policies_id'];
        
        foreach ($rule['policies_id'] as $policy_id) {
            foreach ($user_groups as $user_group) {
                if($value_action[$group_config_object['policies'][$policy_id][$user_group]['action']] > $value_action[$rule['action']]){
                    $rule['action'] = $group_config_object['policies'][$policy_id][$user_group]['action'];
                    $rule['severity'] = $group_config_object['policies'][$policy_id][$user_group]['severity'];
                } else {
                    if($value_severity[$group_config_object['policies'][$policy_id][$user_group]['severity']] > $value_severity[$rule['severity']]){
                        $rule['severity'] = $group_config_object['policies'][$policy_id][$user_group]['severity'];
                    }
                }
            }
        }
        
        $group_policy['rules'][] = $rule;
    }
    
    $group_policy['files'] = array();
    foreach ($group_config_object['files'] as $file_id => $file_object) {
        $file = array();
        $file['id'] = $file_id;
        $file['file'] = $file_object['name'];
        $file['md5'] = $file_object['md5'];
        $file['sha1'] = $file_object['sha1'];
        $file['ssdeep'] = $file_object['ssdeep'];
        $file['action'] = 'log';
        $file['severity'] = 'low';
        $file['policies_id'] = $file_object['policies_id'];
        
        foreach ($file['policies_id'] as $policy_id) {
            foreach ($user_groups as $user_group) {
                if($value_action[$group_config_object['policies'][$policy_id][$user_group]['action']] > $value_action[$file['action']]){
                    $file['action'] = $group_config_object['policies'][$policy_id][$user_group]['action'];
                    $file['severity'] = $group_config_object['policies'][$policy_id][$user_group]['severity'];
                } else {
                    if($value_severity[$group_config_object['policies'][$policy_id][$user_group]['severity']] > $value_severity[$file['severity']]){
                        $file['severity'] = $group_config_object['policies'][$policy_id][$user_group]['severity'];
                    }
                }
            }
        }
        
        $group_policy['files'][] = $file;
    }    
    
    $group_policy['network_places'] = array();
    foreach ($group_config_object['network_places'] as $network_place_id => $network_place_object) {
        $network_place = array();
        $network_place['id'] = $network_place_id;
        $network_place['network_uri'] = $network_place_object['network_uri'];
        $network_place['description'] = $network_place_object['description'];
        $network_place['action'] = 'log';
        $network_place['severity'] = 'low';
        $network_place['policies_id'] = $network_place_object['policies_id'];
        
        foreach ($network_place['policies_id'] as $policy_id) {
            foreach ($user_groups as $user_group) {
                if($value_action[$group_config_object['policies'][$policy_id][$user_group]['action']] > $value_action[$network_place['action']]){
                    $network_place['action'] = $group_config_object['policies'][$policy_id][$user_group]['action'];
                    $network_place['severity'] = $group_config_object['policies'][$policy_id][$user_group]['severity'];
                } else {
                    if($value_severity[$group_config_object['policies'][$policy_id][$user_group]['severity']] > $value_severity[$network_place['severity']]){
                        $network_place['severity'] = $group_config_object['policies'][$policy_id][$user_group]['severity'];
                    }
                }
            }
        }
        
        $group_policy['network_places'][] = $network_place;
    }
    
    $group_policy['applications'] = array();
    foreach ($group_config_object['applications'] as $application_id => $application_object) {
        $application = array();
        $application['id'] = $application_id;
        $application['application'] = $application_object['application'];
        $application['description'] = $application_object['description'];     
        $application['action'] = 'log';
        $application['severity'] = 'low';
        $application['policies_id'] = $application_object['policies_id'];
        
        foreach ($application['policies_id'] as $policy_id) {
            foreach ($user_groups as $user_group) {
                if($value_action[$group_config_object['policies'][$policy_id][$user_group]['action']] > $value_action[$application['action']]){
                    $application['action'] = $group_config_object['policies'][$policy_id][$user_group]['action'];
                    $application['severity'] = $group_config_object['policies'][$policy_id][$user_group]['severity'];
                } else {
                    if($value_severity[$group_config_object['policies'][$policy_id][$user_group]['severity']] > $value_severity[$application['severity']]){
                        $application['severity'] = $group_config_object['policies'][$policy_id][$user_group]['severity'];
                    }
                }
            }
        }
        
        $group_policy['applications'][] = $application;
    }    
    
    $group_policy['endpoint_modules'] = $group_config_object['endpoint_modules'];
    $group_policy['mime_types'] = $group_config_object['mime_types'];
    $group_policy['screenshot_severity'] = $group_config_object['screenshot_severity'];
    $group_policy['block_encrypted'] = $group_config_object['block_encrypted'];
    $group_policy['groups_user'] = $group_config_object['groups_user'];
    
    return $group_policy;
}

function unifyGroups($group_files) {

    $group_config = file_get_contents($group_files[0]);
    $group_policy = unifyGroupConfig($group_config);
    
    $subconcepts = $group_policy['subconcepts'];
    $rules = $group_policy['rules'];
    $files = $group_policy['files'];
    $network_places = $group_policy['network_places'];
    $applications = $group_policy['applications'];
    $screenshot_severity = $group_policy['screenshot_severity'];
    $block_encrypted = $group_policy['block_encrypted'];
    $endpoint_modules = $group_policy['endpoint_modules'];
    $mime_types = $group_policy['mime_types'];
    $groups_user = $group_policy['groups_user'];
    
    if (sizeof($group_files)) {
        $value_action = array(null => 0, 'log' => 1, 'alert' => 2, "block" => 3);
        $value_severity = array(null => 0, 'low' => 1, 'medium' => 2, 'high' => 3);

        foreach (array_slice($group_files, 1) as $group_file) {
            $group_config = file_get_contents($group_file);
            $group_policy = unifyGroupConfig($group_config);

            foreach ($group_policy['subconcepts'] as $subconcept) {
                $add = true;
                foreach ($subconcepts as &$element) {
                    if ($subconcept['id'] == $element['id']) {
                        $add = false;
                        if($value_action[$subconcept['action']] > $value_action[$element['action']]){
                            $element['action'] = $subconcept['action'];
                            $element['severity'] = $subconcept['severity'];
                        } else {
                            if($value_severity[$subconcept['severity']] > $value_severity[$element['severity']]){
                                $element['severity'] = $subconcept['severity'];
                            }
                        }
                        $element['policies_id'] = array_merge($element['policies_id'], $subconcept['policies_id']);
                    }
                }
                if ($add) {
                    $subconcepts[] = $subconcept;
                }
            }

            foreach ($group_policy['rules'] as $rule) {
                $add = true;
                foreach ($rules as &$element) {
                    if ($rule['id'] == $element['id']) {
                        $add = false;
                        if($value_action[$rule['action']] > $value_action[$element['action']]){
                            $element['action'] = $rule['action'];
                            $element['severity'] = $rule['severity'];
                        } else {
                            if($value_severity[$rule['severity']] > $value_severity[$element['severity']]){
                                $element['severity'] = $rule['severity'];
                            }
                        }
                        $element['policies_id'] = array_merge($element['policies_id'], $rule['policies_id']);
                    }
                }
                if ($add) {
                    $rules[] = $rule;
                }
            }

            foreach ($group_policy['files'] as $file) {
                $add = true;
                foreach ($files as &$element) {
                    if ($file['id'] == $element['id']) {
                        $add = false;
                        if($value_action[$file['action']] > $value_action[$element['action']]){
                            $element['action'] = $file['action'];
                            $element['severity'] = $file['severity'];
                        } else {
                            if($value_severity[$file['severity']] > $value_severity[$element['severity']]){
                                $element['severity'] = $file['severity'];
                            }
                        }                        
                        $element['policies_id'] = array_merge($element['policies_id'], $file['policies_id']);
                    }
                }
                if ($add) {
                    $files[] = $file;
                }
            }

            foreach ($group_policy['network_places'] as $network_place) {
                $add = true;
                foreach ($network_places as &$element) {
                    if ($network_place['id'] == $element['id']) {
                        $add = false;
                        if($value_action[$network_place['action']] > $value_action[$element['action']]){
                            $element['action'] = $network_place['action'];
                            $element['severity'] = $network_place['severity'];
                        } else {
                            if($value_severity[$network_place['severity']] > $value_severity[$element['severity']]){
                                $element['severity'] = $network_place['severity'];
                            }
                        }                        
                        $element['policies_id'] = array_merge($element['policies_id'], $network_place['policies_id']);
                    }
                }
                if ($add) {
                    $network_places[] = $network_place;
                }
            }

            foreach ($group_policy['applications'] as $application) {
                $add = true;
                foreach ($applications as &$element) {
                    if ($application['id'] == $element['id']) {
                        $add = false;
                        if($value_action[$application['action']] > $value_action[$element['action']]){
                            $element['action'] = $application['action'];
                            $element['severity'] = $application['severity'];
                        } else {
                            if($value_severity[$application['severity']] > $value_severity[$element['severity']]){
                                $element['severity'] = $application['severity'];
                            }
                        }                        
                        $element['policies_id'] = array_merge($element['policies_id'], $application['policies_id']);
                    }
                }
                if ($add) {
                    $applications[] = $application;
                }
            }
            
            $block_encrypted = $block_encrypted * $group_policy['block_encrypted'];
            $groups_user = array_merge($groups_user, $group_policy['groups_user']);
        }
    }

    $final_policies = array();
    $final_policies['subconcepts'] = $subconcepts;
    $final_policies['rules'] = $rules;
    $final_policies['files'] = $files;
    $final_policies['network_places'] = $network_places;
    $final_policies['applications'] = $applications;
    $final_policies['screenshot_severity'] = $screenshot_severity;
    $final_policies['block_encrypted'] = $block_encrypted;
    $final_policies['endpoint_modules'] = $endpoint_modules;
    $final_policies['mime_types'] = $mime_types;
    $final_policies['groups_user'] = $groups_user;

    return $final_policies;
}

function unify($user_dir) {

    $user_groups = getFileList($user_dir, false);
    if (sizeof($user_groups)) {
        $user_policy = unifyGroups($user_groups);
        echo json_encode($user_policy);
    }

    return 0;
}

function VerifyMatch($code, $match){
    $code = base64_decode($code);
    $match = base64_decode($match);
    $return_val = false;
    eval($code);
    return $return_val;
}

/*
$code = 'ICAgICRzdW0gPSAwOw0KICAgICRhbHQgPSBmYWxzZTsNCiAgICBmb3IgKCRpID0gc3RybGVuKCRtYXRjaCkgLSAxOyAkaSA+PSAwOyAkaS0tKSB7DQogICAgICAgICRuID0gc3Vic3RyKCRtYXRjaCwgJGksIDEpOw0KICAgICAgICBpZiAoJGFsdCkgew0KICAgICAgICAgICAgJG4gKj0gMjsNCiAgICAgICAgICAgIGlmICgkbiA+IDkpIHsNCiAgICAgICAgICAgICAgICAkbiA9ICgkbiAlIDEwKSArIDE7DQogICAgICAgICAgICB9DQogICAgICAgIH0NCiAgICAgICAgJHN1bSArPSAkbjsNCiAgICAgICAgJGFsdCA9ICEkYWx0Ow0KICAgIH0NCg0KICAgICRyZXR1cm5fdmFsID0gKCRzdW0gJSAxMCA9PSAwKTs=';
$match = 'NDU3MTQ2OTAyMDAyMDA2OQ==';
$no_match = 'NDI0OTg2MzM5ODQ=';

if(VerifyMatch($code, $match)){
    echo "\nvalid";
} else {
    echo "\ninvalid";
}
*/
/*
unify($argv[1]);
*/

?>