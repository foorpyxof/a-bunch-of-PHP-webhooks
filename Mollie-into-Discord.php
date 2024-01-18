<?php

    // BEHOLD: ERYNN'S MAGICAL MOLLIE-TO-DISCORD WEBHOOK!!!
    // Made by Erynn Scholtes - January 2024
    // Last updated on 18/jan/2024

    // If the post data does not contain an "id" (which Mollie webhook requests always have), kill the script
    if(!isset($_POST['id']))
    {
        error_log("Nawt gord");
        echo "No no no";
        die();
    }

    \ini_set('display_errors', '1');
    \ini_set('display_startup_errors', '1');
    \error_reporting(\E_ALL);


    // Make sure you define the path to autoload.php! Otherwise it won't know what the fuck you mean by "Mollie"

    require_once // path_to/mollie/vendor/autoload.php

    $discordWebhookMessage = [];

    error_log(isset($_POST['id']) ? $_POST['id'] : "nothing was sent");


    // Determine what Mollie called your webhook for (payment, order)
    // If nothing matches, kill the script.

    if(str_starts_with($_POST['id'], "tr_"))
    {
        $callType = "payment";
    }
    elseif(str_starts_with($_POST['id'], "ord_"))
    {
        $callType = "order";
    }
    else
    {
        die();
    }

    $mollie = new \Mollie\Api\MollieApiClient();


    // Your API-key needs to be set to access Mollie's API service.
    // Your organization ID needs to be set to make a clickable link to the matching page on your mollie dashboard

    $mollie->setApiKey(/* Your API-key goes here! */);
    $orgID = /* Put your organization-ID here!*/;

    switch($callType)
    {

        //////////////////////////////////////////////////    vvv IN CASE THE CALL WAS FOR AN ORDER vvv     //////////////////////////////////////////////////
        case "order":
            $order = $mollie->orders->get($_POST['id']);
            $orderID = $order->metadata->order_id;

            if(($order->isPaid() || $order->isAuthorized()))    // If the order was paid for or payment was authorized:
            {
                $discordWebhookMessage = [
                    "username" => "ORDER-PLACED",
                    "content" => "An order has just been placed by a customer! :)",
                    "embeds" => [
                        [
                            "title" => "Order-ID:\t" . $orderID . sprintf("\nMollie Order-ID:\t%s", $_POST['id']),
                            "type" => "rich",
                            "description" => $order->isPaid() ? "This order was just paid for. Time to ship!\tヽ(*・ω・)ﾉ" : "This order was just authorized. Check the order page, and get ready to ship!\tヽ(*・ω・)ﾉ",
                            "url" => "https://my.mollie.com/dashboard/" . $orgID . "/orders/" . $_POST['id']
                        ]
                    ]
                ];
            }elseif($order->isCanceled())                       // If the order was canceled:
            {
                $discordWebhookMessage = [
                    "username" => "ORDER-CANCELED",
                    "content" => "An order has just been canceled! :(",
                    "embeds" => [
                        [
                            "title" => "Order-ID:\t" . $orderID . sprintf("\nMollie Order-ID:\t%s", $_POST['id']),
                            "type" => "rich",
                            "description" => "Sad to see it go\to(〒﹏〒)o",
                            "url" => "https://my.mollie.com/dashboard/" . $orgID . "/orders/" . $_POST['id']
                        ]
                    ]
                ];
            }elseif($order->isCompleted())                      // If the order is fully shipped:
            {
                $discordWebhookMessage = [
                    "username" => "ORDER-SHIPPED",
                    "content" => "Shippy shippy yay",
                    "embeds" => [
                        [
                            "title" => "Order-ID:\t" . $orderID . sprintf("\nMollie Order-ID:\t%s", $_POST['id']),
                            "type" => "rich",
                            "description" => "A full order has just been shipped!\to(≧▽≦)o",
                            "url" => "https://my.mollie.com/dashboard/" . $orgID . "/orders/" . $_POST['id']
                        ]
                    ]
                ];
            }elseif($order->isExpired())                        // If the order is expired (not paid for in the allotted time):
            {
                $discordWebhookMessage = [
                    "username" => "ORDER-EXPIRED",
                    "content" => "It took too long...",
                    "embeds" => [
                        [
                            "title" => "Order-ID:\t" . $orderID . sprintf("\nMollie Order-ID:\t%s", $_POST['id']),
                            "type" => "rich",
                            "description" => "They didn't feel like paying today... (ﾉಥ益ಥ)ﾉ",
                            "url" => "https://my.mollie.com/dashboard/" . $orgID . "/orders/" . $_POST['id']
                        ]
                    ]
                ];
            }
            break;

        //////////////////////////////////////////////////    ^^^ IN CASE THE CALL WAS FOR AN ORDER ^^^     //////////////////////////////////////////////////
        // ------------------------------------------------------------------------------------------------------------------------------------------------ //
        //////////////////////////////////////////////////    vvv IN CASE THE CALL WAS FOR A PAYMENT vvv    //////////////////////////////////////////////////
        case "payment":
            $payment = $mollie->payments->get($_POST['id']);
            try
            {
                // Try this to determine whether or not the payment is attached to an order.
                $orderID = $payment->orderId;

                // If that's the case, set this discord message
                if($payment->isCanceled())
                {
                    $discordWebhookMessage = [
                        "username" => "PAYMENT-CANCELED",
                        "content" => "This payment- that is attached to an order- has been CANCELED! >:(",
                        "embeds" => [
                            [
                                "title" => sprintf("Order-ID:\t%s\nMollie Order-ID:\t%s\n\nMollie Payment-ID:\t%s", $dbOrderID, $orderID, $_POST['id']),
                                "type" => "rich",
                                "description" => "The audacity! (︶︹︺)\"",
                                "url" => "https://my.mollie.com/dashboard/" . $orgID . "/payments/" . $_POST['id']
                            ]
                        ]
                    ];
                }

                // Otherwise, execute "catch" instead
            }catch(Exception $e)
            {
                // If the payment is fully fulfilled, set this discord message
                if($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks())
                {
                    $discordWebhookMessage = [
                        "username" => "PAYMENT-RECEIVED",
                        "content" => "This payment has been completed!",
                        "embeds" => [
                            [
                                "title" => sprintf("Mollie Payment-ID:\t%s", $_POST['id']),
                                "type" => "rich",
                                "description" => "Payment? Paid! (づ ◕‿◕ )づ",
                                "url" => "https://my.mollie.com/dashboard/" . $orgID . "/payments/" . $_POST['id']
                            ]
                        ]
                    ];
                // If the payment is canceled, set this discord message
                }elseif($payment->isCanceled())
                {
                    $discordWebhookMessage = [
                        "username" => "PAYMENT-CANCELED",
                        "content" => "This payment has been CANCELED! >:(",
                        "embeds" => [
                            [
                                "title" => sprintf("Mollie Payment-ID:\t%s", $_POST['id']),
                                "type" => "rich",
                                "description" => "The audacity! (︶︹︺)\"",
                                "url" => "https://my.mollie.com/dashboard/" . $orgID . "/payments/" . $_POST['id']
                            ]
                        ]
                    ];
                }
            }
            break;
        //////////////////////////////////////////////////    ^^^ IN CASE THE CALL WAS FOR A PAYMENT ^^^    //////////////////////////////////////////////////
        }

    $webhookURL = /* Your DISCORD WEBHOOK url (string) goes here! :3 */;
    $ch = curl_init($webhookURL);
    

    // Change some settings
    curl_setopt_array( $ch, [
        CURLOPT_URL => $webhookURL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($discordWebhookMessage),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => 1
    ]);

    // FULL SEND
    $curlResponse = curl_exec($ch);
    curl_close($ch);
    unset($ch, $curlResponse);
?>