<?php

(defined('BASEPATH')) OR exit('No direct script access allowed');

class MY_Loader extends CI_Loader {

    protected $_ci_events_paths = array();

    public function __construct() {
        parent::__construct();

        $this->_ci_events_paths = array(APPPATH);
        log_message('debug', "MY_Loader Class Initialized");
    }

    /** Load a events module * */
    public function event($event = '', $params = NULL, $object_name = NULL)
    {
        if (empty($event))
		{
			return $this;
		}
		elseif (is_array($event))
		{
			foreach ($event as $key => $value)
			{
				if (is_int($key))
				{
					$this->event($value, $params);
				}
				else
				{
					$this->event($key, $params, $value);
				}
			}

			return $this;
		}

		if ($params !== NULL && ! is_array($params))
		{
			$params = NULL;
		}

		$this->_ci_load_event($event, $params, $object_name);
		return $this;
    }

    
    /** Load an array of events * */
    public function events($event = '', $params = NULL, $object_name = NULL)
    {
        if (is_array($event))
        {
            foreach ($event as $class)
            {
                $this->event($class, $params);
            }
        }
        return;
    }


    protected function _ci_load_event($class, $params = NULL, $object_name = NULL)
    {
        // Get the class name, and while we're at it trim any slashes.
        // The directory path can be included as part of the class name,
        // but we don't want a leading slash
        $class = str_replace('.php', '', trim($class, '/'));

        // Was the path included with the class name?
        // We look for a slash to determine this
        if (($last_slash = strrpos($class, '/')) !== FALSE)
        {
            // Extract the path
            $subdir = substr($class, 0, ++$last_slash);

            // Get the filename from the path
            $class = substr($class, $last_slash);
        }
        else
        {
            $subdir = '';
        }

        $class = ucfirst($class);

        // Is this a stock library? There are a few special conditions if so ...
        if (file_exists(BASEPATH.'events/'.$subdir.$class.'.php'))
        {
            return $this->_ci_load_stock_event($class, $subdir, $params, $object_name);
        }

        // Safety: Was the class already loaded by a previous call?
        if (class_exists($class, FALSE))
        {
            $property = $object_name;
            if (empty($property))
            {
                $property = strtolower($class);
                isset($this->_ci_varmap[$property]) && $property = $this->_ci_varmap[$property];
            }

            $CI =& get_instance();
            if (isset($CI->$property))
            {
                log_message('debug', $class.' class already loaded. Second attempt ignored.');
                return;
            }

            return $this->_ci_init_library($class, '', $params, $object_name);
        }

        // Let's search for the requested library file and load it.
        foreach ($this->_ci_events_paths as $path)
        {
            // BASEPATH has already been checked for
            if ($path === BASEPATH)
            {
                continue;
            }

            $filepath = $path.'events/'.$subdir.$class.'.php';
            // Does the file exist? No? Bummer...
            if ( ! file_exists($filepath))
            {
                continue;
            }

            include_once($filepath);
            return $this->_ci_init_library($class, '', $params, $object_name);
        }

        // One last attempt. Maybe the library is in a subdirectory, but it wasn't specified?
        if ($subdir === '')
        {
            return $this->_ci_load_library($class.'/'.$class, $params, $object_name);
        }

        // If we got this far we were unable to find the requested class.
        log_message('error', 'Unable to load the requested class: '.$class);
        show_error('Unable to load the requested class: '.$class);
    }

    protected function _ci_load_stock_event($event_name, $file_path, $params, $object_name)
    {
        $prefix = 'CI_';

        if (class_exists($prefix.$event_name, FALSE))
        {
            if (class_exists(config_item('subclass_prefix').$event_name, FALSE))
            {
                $prefix = config_item('subclass_prefix');
            }

            $property = $object_name;
            if (empty($property))
            {
                $property = strtolower($event_name);
                isset($this->_ci_varmap[$property]) && $property = $this->_ci_varmap[$property];
            }

            $CI =& get_instance();
            if ( ! isset($CI->$property))
            {
                return $this->_ci_init_library($event_name, $prefix, $params, $object_name);
            }

            log_message('debug', $event_name.' class already loaded. Second attempt ignored.');
            return;
        }

        $paths = $this->_ci_events_paths;
        array_pop($paths); // BASEPATH
        array_pop($paths); // APPPATH (needs to be the first path checked)
        array_unshift($paths, APPPATH);

        foreach ($paths as $path)
        {
            if (file_exists($path = $path.'events/'.$file_path.$event_name.'.php'))
            {
                // Override
                include_once($path);
                if (class_exists($prefix.$event_name, FALSE))
                {
                    return $this->_ci_init_library($event_name, $prefix, $params, $object_name);
                }

                log_message('debug', $path.' exists, but does not declare '.$prefix.$event_name);
            }
        }

        include_once(BASEPATH.'events/'.$file_path.$event_name.'.php');

        // Check for extensions
        $subclass = config_item('subclass_prefix').$event_name;
        foreach ($paths as $path)
        {
            if (file_exists($path = $path.'events/'.$file_path.$subclass.'.php'))
            {
                include_once($path);
                if (class_exists($subclass, FALSE))
                {
                    $prefix = config_item('subclass_prefix');
                    break;
                }

                log_message('debug', $path.' exists, but does not declare '.$subclass);
            }
        }

        return $this->_ci_init_library($event_name, $prefix, $params, $object_name);
    }

}
