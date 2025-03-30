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
 * Main application class responsible for handling incoming requests and routing them
 * to the appropriate controller methods. It also manages the application version
 * and initializes the main controller.
 *
 * @package Webklex\CalMag
 */
class Application {

    /**
     * @var string VERSION The current semantic version of the application.
     */
    const VERSION = "2.4.1";

    /**
     * @var Controller $controller The main controller instance used to handle actions.
     */
    protected Controller $controller;


    /**
     * Application constructor.
     * Initializes the main controller.
     */
    public function __construct() {
        $this->controller = new Controller();
    }

    /**
     * Route the incoming HTTP request to the appropriate controller action.
     * This method determines the action based on the request method (GET/POST),
     * URL parameters, and request headers (e.g., for API calls).
     *
     * @return void
     */
    public function route(): void {
        // Ensure PHP session is started to handle user-specific data like language preference.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            error_log('Session started with ID: ' . session_id()); // Log session start for debugging
        }
        
        // Initialize an empty payload array to store request data.
        $payload = [];

        // Special route for handling language switching.
        if ($_SERVER['REQUEST_URI'] === '/switch-language') {
            // Enable detailed error reporting specifically for this sensitive endpoint.
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            // Handle POST request to change the language.
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Log the raw incoming request body for debugging.
                $rawInput = file_get_contents('php://input');
                error_log('Raw input: ' . $rawInput);
                
                // Attempt to parse the incoming JSON data.
                $input = json_decode($rawInput, true);
                error_log('Parsed input: ' . print_r($input, true)); // Log parsed data.
                
                // Log potential JSON parsing errors.
                if ($input === null) {
                    error_log('JSON decode error: ' . json_last_error_msg());
                }
                
                // Double-check session status before accessing session data.
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                
                // Call the controller method to handle the language switch logic.
                $this->controller->switchLanguage($input);
                return; // Stop further processing after handling the language switch.
            
            // Handle GET request to retrieve current language status.
            } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Double-check session status.
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                
                // Respond with JSON containing the current language settings and session info.
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'locale' => Translator::getInstance()->getCurrentLocale(), // Get locale from Translator service
                    'session_locale' => $_SESSION['locale'] ?? null, // Get locale stored in session
                    'session_id' => session_id(),
                    'session_status' => session_status()
                ]);
                exit; // Stop execution after sending the JSON response.
            }
        }

        // Determine payload based on request method.
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Check for a 'p' parameter in the URL, which might contain base64 encoded JSON payload (for sharing results).
            $_payload = $_GET["p"] ?? "";

            // If 'p' parameter exists, decode and parse it.
            if ($_payload !== "") {
                $_payload = base64_decode($_payload); // Decode from base64
                $_payload = json_decode($_payload, true); // Decode JSON string into an array
                // Use the decoded payload if it's a valid array.
                if (is_array($_payload)) {
                    $payload = $_payload;
                }
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Check if the request expects a JSON response or sends JSON data (API call).
            if (isset($_SERVER["HTTP_ACCEPT"]) && $_SERVER["HTTP_ACCEPT"] === "application/json" ||
                isset($_SERVER["CONTENT_TYPE"]) && $_SERVER["CONTENT_TYPE"] === "application/json") {
                // Read raw POST body and decode JSON.
                $payload = json_decode(file_get_contents('php://input'), true);
                // Ensure payload is an array, even if JSON decoding fails or returns non-array.
                if (!is_array($payload)) {
                    $payload = [];
                }
                // Route to the API controller method.
                $this->controller->api($payload);
                return; // Stop further processing after handling the API request.
            }
            // For regular form submissions (not JSON API calls), use the $_POST superglobal.
            $payload = $_POST;
        }

        // --- Routing based on GET parameters or payload content ---

        // If 'compare' GET parameter is set, route to the comparison controller method.
        if (isset($_GET["compare"])) {
            $this->controller->compare($payload);
            return;
        }

        // If 'builder' GET parameter is set, route to the builder controller method.
        if (isset($_GET["builder"])) {
            $this->controller->builder($payload);
            return;
        }

        // If the payload contains data (likely from a form submission or shared link),
        // route to the result controller method, passing the extracted data.
        if (count($payload) > 0) {
            $this->controller->result([
                // Map payload keys to expected parameter names, providing defaults.
                "fertilizer"             => $payload["fertilizer"] ?? "",
                "additive"               => $payload["additive"] ?? [],
                "ratio"                  => $payload["ratio"] ?? 3.5, // Default Ca:Mg ratio
                "target_offset"          => $payload["target_offset"] ?? 0.0, // Default target offset
                "volume"                 => $payload["volume"] ?? 5.0, // Default water volume
                "support_dilution"       => $payload["support_dilution"] ?? true, // Default dilution support
                "region"                 => $payload["region"] ?? "us", // Default region (e.g., for units)
                "elements"               => $payload["elements"] ?? [], // Custom fertilizer elements
                "element_units"          => $payload["element_units"] ?? [], // Units for custom elements
                "additive_concentration" => $payload["additive_concentration"] ?? [], // Custom additive concentrations
                "additive_units"         => $payload["additive_units"] ?? [], // Units for custom additives
                "additive_elements"      => $payload["additive_elements"] ?? [], // Elements in custom additives
                "fertilizer_elements"    => $payload["fertilizer_elements"] ?? [], // Elements in custom fertilizer
                "target_weeks"           => $payload["target_weeks"] ?? [], // Target weeks for calculation
                "target_calcium"         => $payload["target_calcium"] ?? [], // Target calcium levels per week
                "target_magnesium"       => $payload["target_magnesium"] ?? [], // Target magnesium levels per week
            ]);
            return;
        }

        // If no specific route matches and no payload is present, show the default index page.
        $this->controller->index();
    }
}
