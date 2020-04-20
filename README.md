Receive instant push notification for new articles on your [Tiny Tiny RSS](https://tt-rss.org/)
instance.

1. Which articles get pushed are entirely configurable.
2. Click to open the article, and it will mark it as read on Tiny Tiny RSS.

## Supported browsers
I have not tested all of them, but it should work for:
1. Chrome 50+
2. Firefox 
3. Microsoft Edge 17+

IE and Safari are explicitly not supported. Other Chromium/Blink-based browsers might support it.

## Runtime requirements
We recommend PHP 7+, while PHP 5.6 might still work. You need the following dependencies,
in addition to what Tiny Tiny RSS already requires:
1. curl
2. openssl
3. gmp

## How to use
1. Ensure you have all the runtime requirements stated above. If not, install them.
2. Download the [latest release](https://github.com/powerivq/ttrss-pusher/releases/tag/0.9) here
3. Extract it into your Tiny Tiny RSS plugins.local folder
4. It is a system plugin, therefore you need to [turn it on in the config.php file](https://git.tt-rss.org/fox/tt-rss/wiki/Plugins). The plugin's name is `pusher`
5. In user preference, create a filter that triggers this plugin to send push notifications

![config-pusher](https://user-images.githubusercontent.com/1321403/79706856-7be7f000-826f-11ea-971f-f0b38dd3b139.png)

## Style
### On Windows
<img src="https://user-images.githubusercontent.com/1321403/79706886-91f5b080-826f-11ea-8e81-2e07ba4b1b68.png" width="385" height="422">

## Dev dependencies
In order to build the project, you need:
1. PHP 7+
2. NodeJS LTS
3. Yarn

## License
Licensed under MIT.
