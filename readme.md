# HTTP Thingie

Generate listings of a directory and its sub-directories outside the public web directory and serve the media files found there. The project’s main goal is to serve media files in a local network where authentication is not necessary. The experience is optimized for users of iOS and iPadOS to browse for a movie and use AirPlay to have it play on a larger TV.

## How it works

Loading the `public/index.php` script through a web server will create a listing of the directory specified wit the constant `ROOT_DIR` in `thingie.php`. If a symbol link exists at `public/download` pointing to the same directory then the file will not be served through PHP but directly by the web server. This is especially beneficial for files larger than 2 GB and 32-bit PHP installations.

The web server should be configure so that all non-existing files are re-routed to `public/index.php`.

## How to configure Apache

The included `public/.htaccess` file should be sufficient to route all non-existing requests to `public/index.php`.

## How to configure nginx

Use the included `nginx.conf` file as a starting point.

## Permissions

If the `DEBUG` constant is set to `true` in `thingie.php` the project’s directory must have the necessary permissions for the web server process to write there.