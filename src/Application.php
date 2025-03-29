<?php
/*
* File: Application.php
* Category: -
* Author: M.Goldenbaum
* Created: 08.11.24 19:29
* Updated: -
*
* Description:
*  -
*/

namespace Webklex\CalMag;

/**
 * Class Application
 *
 * @package Webklex\CalMag
 */
class Application {

    /**
     * @var string VERSION The current version of the application
     */
    const VERSION = "2.4.1";

    /**
     * @var Controller $controller The controller instance
     */
    protected Controller $controller;


    /**
     * Application constructor.
     */
    public function __construct() {
        $this->controller = new Controller();
    }

    /**
     * Route the request
     * @return void
     */
    public function route(): void {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            error_log('Session started with ID: ' . session_id());
        }
        
        $payload = [];

        // Handle language switch
        if ($_SERVER['REQUEST_URI'] === '/switch-language') {
            // Enable error reporting for debugging
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Log the raw input
                $rawInput = file_get_contents('php://input');
                error_log('Raw input: ' . $rawInput);
                
                // Parse JSON input
                $input = json_decode($rawInput, true);
                error_log('Parsed input: ' . print_r($input, true));
                
                if ($input === null) {
                    error_log('JSON decode error: ' . json_last_error_msg());
                }
                
                // Ensure session is active
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                
                $this->controller->switchLanguage($input);
                return;
            } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Ensure session is active
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                
                // Return current language status
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'locale' => Translator::getInstance()->getCurrentLocale(),
                    'session_locale' => $_SESSION['locale'] ?? null,
                    'session_id' => session_id(),
                    'session_status' => session_status()
                ]);
                exit;
            }
        }

        // Check if the current request is a POST request
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_payload = $_GET["p"] ?? "";

            // Check if a shared payload is present
            if ($_payload !== "") {
                $_payload = base64_decode($_payload);
                $_payload = json_decode($_payload, true);
                if (is_array($_payload)) {
                    $payload = $_payload;
                }
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Check for potential json request and call the api if this is the case
            if ($_SERVER["HTTP_ACCEPT"] === "application/json" || $_SERVER["CONTENT_TYPE"] === "application/json") {
                $payload = json_decode(file_get_contents('php://input'), true);
                if (!is_array($payload)) {
                    $payload = [];
                }
                $this->controller->api($payload);
                return;

            }
            $payload = $_POST;
        }


        if (isset($_GET["compare"])) {
            $this->controller->compare($payload);
            return;
        }

        if (isset($_GET["builder"])) {
            $this->controller->builder($payload);
            return;
        }

        if (count($payload) > 0) {
            $this->controller->result([
                                          "fertilizer"             => $payload["fertilizer"] ?? "",
                                          "additive"               => $payload["additive"] ?? [],
                                          "ratio"                  => $payload["ratio"] ?? 3.5,
                                          "target_offset"          => $payload["target_offset"] ?? 0.0,
                                          "volume"                 => $payload["volume"] ?? 5.0,
                                          "support_dilution"       => $payload["support_dilution"] ?? true,
                                          "region"                 => $payload["region"] ?? "us",
                                          "elements"               => $payload["elements"] ?? [],
                                          "element_units"          => $payload["element_units"] ?? [],
                                          "additive_concentration" => $payload["additive_concentration"] ?? [],
                                          "additive_units"         => $payload["additive_units"] ?? [],
                                          "additive_elements"      => $payload["additive_elements"] ?? [],
                                          "fertilizer_elements"    => $payload["fertilizer_elements"] ?? [],
                                          "target_weeks"           => $payload["target_weeks"] ?? [],
                                          "target_calcium"         => $payload["target_calcium"] ?? [],
                                          "target_magnesium"       => $payload["target_magnesium"] ?? [],
                                      ]);
            return;
        }

        // Default to the index page
        $this->controller->index();
    }

}