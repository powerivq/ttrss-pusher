<?php

require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;

class Pusher extends Plugin implements IHandler {
    const PRIVATE_KEY_PROP = 'privateKey';
    const PUBLIC_KEY_PROP = 'publicKey';
    const SUBSCRIPTION_PROP = 'subscription';
    
    private $plugin_host;

    function about() {
        return array(1.0,
            'Show push notification for new posts',
            'powerivq',
            true);
    }

    function init($host) {
        $this->plugin_host = $host;
        $this->plugin_host->load_data();
        $this->plugin_host->add_handler('pusher', 'update_subscription', $this);
        $this->plugin_host->add_handler('pusher', 'mark_read', $this);
        $this->plugin_host->add_handler('pusher', 'worker', $this);
        $this->plugin_host->add_filter_action($this, 'push', 'Send push notification to browsers');
        $this->init_keys();
    }

    function init_keys() {
        if ($this->plugin_host->get($this, self::PRIVATE_KEY_PROP)
                && $this->plugin_host->get($this, self::PUBLIC_KEY_PROP)) {
            return;
        }

        $keys = VAPID::createVapidKeys();
        $this->plugin_host->set($this, self::PRIVATE_KEY_PROP, $keys[self::PRIVATE_KEY_PROP], false);
        $this->plugin_host->set($this, self::PUBLIC_KEY_PROP, $keys[self::PUBLIC_KEY_PROP]);
    }

    function get_excerpt_img($html) {
        $ret = array();

        $doc = new DOMDocument();
        if (strpos($html, '<html') === false) {
            $html = '<?xml encoding="utf-8"><html><body>' . $html . '</body></html>';
        }
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        foreach ($xpath->query('//img') as $img) {
            $ret['image'] = $img->getAttribute('src');
            break;
        }

        foreach (['h1', 'h2', 'h3', 'h4', 'h5'] as $tag) {
            foreach ($xpath->query('//' . $tag) as $h) {
                $h->parentNode->removeChild($h);
            }
        }

        $content = $doc->saveHTML($doc->documentElement);
        $content = strip_tags(html_entity_decode($content));
        $content = preg_replace('/^[\p{Z}\s\r\n]+/u', '', $content);
        $content = preg_replace('/[\p{Z}\s\r\n]+/u', ' ', $content);
        $ret['excerpt'] = mb_substr($content, 0, 200);
        return $ret;
    }

    function hook_article_filter_action($article, $action) {
        $params = array_merge(array(
            'title' => $article['title'],
            'link' => $article['link'], 
            'guid' => $article['guid_hashed'],
            'uid' => $article['owner_uid'],
        ), $this->get_excerpt_img($article['content']));

        $defaultOptions = [
            'TTL' => 600,
        ];

        $auth = [
            'VAPID' => [
                'subject' => 'mailto:noreply@github.com',
                'publicKey' => $this->plugin_host->get($this, self::PUBLIC_KEY_PROP),
                'privateKey' => $this->plugin_host->get($this, self::PRIVATE_KEY_PROP),
            ],
        ];

        $payload = json_encode($params);
        $subscriptions = $this->plugin_host->get($this, self::SUBSCRIPTION_PROP);
        if ($subscriptions === false) return $article;
        $webPush = new WebPush($auth, $defaultOptions);
        $webPush->setDefaultOptions($defaultOptions);
        foreach (unserialize($subscriptions) as $sub) {
            $sub = (array) $sub;
            if (isset($sub['keys'])) $sub['keys'] = (array) $sub['keys'];
            $subscription = Subscription::create((array) $sub);
            $webPush->sendNotification($subscription, $payload);
        }
        foreach ($webPush->flush() as $report) {
            error_log(($report->isSuccess() ? 'Succeed' : 'Failed') . ': ' . $report->getReason());
        }
        return $article;
    }

    function mark_read() {
        $uid = $_SESSION['uid'];
        $guid = $_POST['guid'];

        $sth = $this->plugin_host->get_pdo()->prepare('UPDATE ttrss_user_entries ue
            JOIN ttrss_entries e ON (e.id = ue.ref_id)
            SET ue.unread=0 WHERE e.guid=? AND ue.owner_uid=?');
        $sth->execute([$guid, $uid]);
    }

    function update_subscription() {
        $subscription = json_decode($_POST['subscription']);
        $browser_id = $_POST['browserId'];

        $sub_str = $this->plugin_host->get($this, self::SUBSCRIPTION_PROP);
        $sub = $sub_str === false ? array() : unserialize($sub_str);
        if ($subscription === null) {
            unset($sub[$browser_id]);
        } else {
            $sub[$browser_id] = $subscription;
        }
        $this->plugin_host->set($this, self::SUBSCRIPTION_PROP, serialize($sub));
    }

    function worker() {
        header('content-type: application/javascript');
        echo file_get_contents(__DIR__ . "/worker.js");
    }

    function csrf_ignore($method) {
        return true;
    }

    function before($method) {
        return true;
    }

    function after() {
    }

    function get_js() {
        $publicKey = $this->plugin_host->get($this, self::PUBLIC_KEY_PROP);
        return "window.pusherPublicKey='$publicKey';" . file_get_contents(__DIR__ . "/main.js");
    }

    function api_version() {
        return 2;
    }
}
