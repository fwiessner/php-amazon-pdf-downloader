<?php
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\Remote\DesiredCapabilities;

class amazon
{

    function __construct($username,$password)
    {
        $this->amazon_user = $username;
        $this->amazon_pass = $password;
        $this->amazon_url = "https://www.amazon.de";
        
        $this->cookie_file = "/tmp/cookiefile";

        $this->client = \Symfony\Component\Panther\Client::createChromeClient('/usr/bin/chromedriver', 
            ['--headless', '--no-sandbox'], 
            [
            'capabilities' => [
                'goog:loggingPrefs' => [
                    'browser' => 'ALL', // calls to console.* methods
                    'performance' => 'ALL' // performance data
                ]
            ]
        ]);

    }


    function amazon_download_files($save_dir = null)
    {

        
        if (isset($save_dir)) {
            if (!file_exists($save_dir)) {
                die("Save Directory does not exist!");
            }
        } else {
            $save_dir = "/tmp/"; 
        }
 
        
        $this->client->followRedirects(true);
        $size = new WebDriverDimension(1600, 1500);
        $this->client->manage()
        ->window()
        ->setSize($size);
        
        $crawler = $this->client->request('GET', $this->amazon_url);
        
        //$this->client->takeScreenshot('screen01.png'); // Yeah, screenshot!
        
        $this->client->waitForVisibility('div.nav-signin-tooltip-footer');
        //$this->client->takeScreenshot('screen02.png'); // Yeah, screenshot!
        
        
        //$this->client->executeScript("document.querySelector('li[title=\'Anwendungsauswahl\'] span[class=\'ng-binding\']').click();");
        $this->client->clicklink('Hallo, Anmelden Konto und Listen');
        //$this->client->takeScreenshot('screen03.png'); // Yeah, screenshot!
        
        $this->client->findElement(WebDriverBy::cssSelector('input#ap_email'))->sendKeys($this->amazon_user);
        //$this->client->takeScreenshot('screen04.png'); // Yeah, screenshot!
        

        $this->client->findElement(WebDriverBy::xpath('//input[@id=\'continue\']'))->click();
        
        
        //$this->client->takeScreenshot('screen05.png'); // Yeah, screenshot!
        $this->client->findElement(WebDriverBy::cssSelector('input#ap_password'))->sendKeys($this->amazon_pass);
        //$this->client->takeScreenshot('screen06.png'); // Yeah, screenshot!
        $this->client->findElement(WebDriverBy::xpath('//input[@id=\'signInSubmit\']'))->click();
        
        $driver = $this->client->getWebDriver();
        $log = $driver->manage()->getLog("performance");
        
        array_reverse($log);
        foreach ($log as $log_element) { 
            $log_entry = json_decode($log_element["message"]);
            if ($log_entry->message->method == "Network.requestWillBeSent" && $log_entry->message->params->documentURL == "https://www.amazon.de/ap/signin?openid.pape.max_auth_age=0&openid.return_to=https%3A%2F%2Fwww.amazon.de%2F%3Fref_%3Dnav_ya_signin&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=deflex&openid.mode=checkid_setup&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&") {
                if (isset($log_entry->message->params->request->headers)) {
                    $header = $log_entry->message->params->request->headers;
                    $header = (array) $header;
                    break;
                }
                
            }
            
        }

        
   

        
        $this->client->request('GET','https://www.amazon.de/gp/your-account/order-history?opt=ab&digitalOrders=0&unifiedOrders=0&returnTo=&__mk_de_DE=%C3%85M%C3%85%C5%BD%C3%95%C3%91&orderFilter=months-6');
       
        $crawler = $this->client->refreshCrawler();

  
        $bdis = $crawler->filterXPath('//bdi');
        foreach ($bdis as $bdi) { 
            $bdi_text = $bdi->getText(); 
            $orders[] = $bdi_text; 
            
        }
       
        
        foreach ($orders as $order_id) { 

            $relreqid = "";
            $get_url = "https://www.amazon.de/gp/shared-cs/ajax/invoice/invoice.html?orderId=$order_id&relatedRequestId=$relreqid&isADriveSubscription=&isBookingOrder=0";
            
            $crawler = $this->client->request('GET', $get_url);
            //get the file and save it
            $html = $crawler->html();
            $links = $crawler->filterXPath('//a');
            foreach ($links as $link) { 
                $href = $link->getAttribute('href');
                if (preg_match("/download/",$href) && preg_match("/invoice/",$href)) {
                    $download_links[$order_id][] = $href;
                }
                
            }
        }

        $cookies = $this->client->getCookieJar()->all();
        foreach ($cookies as $c) {
            $curl_cookies[$c->getName()] = $c->getValue();
        }
        
        $cookie_string = "";
        foreach ($curl_cookies as $name => $value) {
            $cookie_string .= $name."=".$value."; ";
        }
        
        $header["Cookie"] = $cookie_string;
        
        
        foreach ($header as $k => $v) {
            $curl_header[] = "$k: $v";
        }
        
        
        foreach ($download_links as $order_id => $links) { 
            foreach ($links as $link) { 
                $get_url = "https://www.amazon.de/".$link ; 

                
                $parts = preg_split("/\//",$link);
                
                $file_id = $parts[3];
                $filename = $order_id."-".$file_id.".pdf";
                
                if (!file_exists($save_dir."/".$filename)) { 
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $get_url);
                curl_setopt($ch, CURLOPT_VERBOSE, false);
                
                
                curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_header);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                $file = $result;
                
                
                

                file_put_contents($save_dir."/".$filename,$file);
                $downloaded[] = "downloaded: ". $save_dir."/".$filename ;
                } else {
                    $downloaded[] = "skipped: ". $save_dir."/".$filename ;
                }
            }
        }
        return $downloaded;
        
    }


}