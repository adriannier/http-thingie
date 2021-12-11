<?php

// Setup error handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/functions.php';

try {
    
    // Control debug mode
    define('DEBUG', false);
        
    // Set the root directory
    define('ROOT_DIR', '/share/Media');

    // Set the root title
    define('ROOT_TITLE', 'Media');

    // Set path to current web directory
    define('WEB_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR);
        
    // Specify the name of the symbolic link to the root directory
    define('SYMBOLIC_LINK_NAME', 'download');
    
    // 32-bit PHP has a hard time getting sizes for files larger than 2 GB; use this constant to simply skip the check
    define('SKIP_CHECKING_FILE_SIZES', true);
       
    if (debugMode()) {
        
        // Store the start time of this script
        $GLOBALS['startTime'] = microtime(true);
            
        // Setup log file path
        $logDirectory = dirname(__FILE__);
    
        if (!endsWith($logDirectory, DIRECTORY_SEPARATOR)) {
            $logDirectory .= DIRECTORY_SEPARATOR;
        }
        
        define('DEBUG_LOG_FILE', $logDirectory.'debug.log');
    
        ini_set('display_errors', 1);
        
   }
    
    // Sanitize and check root dir
    $sanitizedRootPath = realpath(ROOT_DIR);
    if ($sanitizedRootPath === false) {
        throwException(404, 'Path specified for ROOT_DIR does not exist');
    } else if (!is_dir($sanitizedRootPath)) {
        throwException(403, 'Path specified for ROOT_DIR is not a directory');
    } else if (!is_readable($sanitizedRootPath)) {
        throwException(403, 'Path specified for ROOT_DIR is not readable');
    } else {
      define('ROOT_DIR_SANITIZED', $sanitizedRootPath.DIRECTORY_SEPARATOR);  
    }
    
    // Check for symbolic link
    if (is_link(WEB_DIR.SYMBOLIC_LINK_NAME)) {
        if (!readlink(WEB_DIR.SYMBOLIC_LINK_NAME) === ROOT_DIR_SANITIZED) {
            throwException(500, 'Symbolic link does not have the same destination as ROOT_DIR');
        } else {
            define('USE_SYMLINK', true);
        }
    } else {
        define('USE_SYMLINK', false);
    }

    // Determine all conditions for this to be a vaild request
    $validRequest = isset($_SERVER) && 
        isset($_SERVER['REMOTE_ADDR']) && 
        isset($_SERVER['REQUEST_URI']);
    
    if ($validRequest) {
    
        // Valid request received
        
        // Get the requested path
        $requestedPath = urldecode(parse_url($_SERVER['REQUEST_URI'])['path']);
        
        // Replace obvious mistakes
        if ($requestedPath == '/index.php') {
            $requestedPath = '/';
        }
        
        // Convert requested path to a path for the local file system
        $resourcePath = realpath(ROOT_DIR_SANITIZED.$requestedPath);
        
        if ($resourcePath === false) {
            
            // Invalid path that does not start with ROOT_DIR
            // debugLog('realpath() returned false for '.ROOT_DIR.$requestedPath);
            throwException(404);
        }
        
        if (is_dir($resourcePath) && !endsWith($resourcePath, DIRECTORY_SEPARATOR)) {
            $resourcePath .= DIRECTORY_SEPARATOR;
        } 
         
        if (!startsWith($resourcePath, ROOT_DIR_SANITIZED)) {
            
            // Invalid path that does not start with ROOT_DIR_SANITIZED
            // debugLog('Expected rooth path missing in '.$resourcePath);
            throwException(400);

        } else if (!is_readable($resourcePath)) {
            
            // Path not readable
            // debugLog('Resource is not readble at '.$resourcePath);
            throwException(403);
                        
        } else {
             
            // The requested path can’t be trusted; redefine it by shortening the actual path
            $requestedPath = shortenPath($resourcePath);
        
            // Log request information
            debugLog('Request for '.$requestedPath.' from '.$_SERVER['REMOTE_ADDR']);

            // Find out what platform the request is coming from
            initializePlatform();
            
            // Start session so we can cache file sizes
            session_start();  
        
            if (is_dir($resourcePath)) {
     
                // Handle directory
                       
                $list = listDirectory($resourcePath);
                    
                if ($list !== false) {
                
                    $listing = composeHTMLListing($list, $requestedPath, $resourcePath);
                    
                    // debugLog('Sending HTML with a size of '.fileSizeDisplay(strlen($listing)));
                    
                    echo $listing;
                    
                } else {
                    
                    throwException(403);
                    
                }
            
            } else if (is_file($resourcePath)) {
                
                // Handle directory
                                
            	download($resourcePath);
                
            } else {
                
                // This should not happen at this point
                
                // debugLog('Resource at path neither directory nor file. This should not happen.');
                throwException(404);
                
            }
       
        }
        
        scriptExit(0);
        
    } else {
        
        throwException(1000, 'Can’t execute without request');
        
    }
    
} catch (Exception $e) {

    answerWithException($e);
        
}

function scriptExit($n = 0) {
    
    if (debugMode()) {
    
        $duration = microtime(true) - $GLOBALS['startTime'];
        // debugLog('Exiting with code '.$n.' after '.round($duration * 1000, 4).' ms');
        
    }
    
 
    exit($n);   
}

function throwException($code, $msg = '') {
    
    if (empty($msg)) {
        
        switch ($code) {
            case 400:
                $msg = 'Bad request';
                break;
            case 403:
                $msg = 'Forbidden';
                break;
            case 404:
                $msg = 'Resource not found';
                break;
            case 416:
                $msg = 'Requested Range Not Satisfiable';
                break;
            case 500:
                $msg = 'Internal Server Error';
                break;
            default:
                $msg = "Something went wrong. Error number is $code.";
        }

    }
        
    throw new Exception($msg, $code);
    
}

function answerWithException($exception) {

    $code = $exception->getCode();
    $msg = $exception->getMessage().' ('.$code.')';
    
    
    if ($code >= 100 && $code < 600) {
        http_response_code($code);
    }
    
    // debugLog('[Exception] '.$msg);
    
	$html = composeHTMLPage("Error $code", "<div class=\"error\">$msg</div>");
	
	// debugLog('Sending error page with a size of '.fileSizeDisplay(strlen($html)));
	
	echo $html;
	
	scriptExit(1);

}

function sendHeader($str) {
    
    // debugLog('[Header] '.$str);
    header($str);
}

function debugMode() {
    
    if (defined('DEBUG') && DEBUG === true) {
        
        return true;
        
    } else {
        
        return false;
        
    }
    
}