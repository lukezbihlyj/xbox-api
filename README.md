# Xbox API

An open-source API that enables developers to fetch Xbox Live profile and achievement data. I no longer support nor maintain this project, as time doesn't allow for it. If you want to be able to pull to this repository and maintain it for me, then let me know via email or something.

## Documentation

There are currently 3 methods available, which you can find in the /1.0.0/includes/classes/api.class.php file. This class extends the Base class, which is in /includes/classes/ and holds the core functions for accessing information from xbox.com. You can look through the /1.0.0/ files to see which methods to call and how to setup the class.

### Profile

```php
$data = $api->fetch_profile($gamertag);
```

### Games

```php
$data = $api->fetch_games($gamertag);
```

### Achievements

```php
$data = $api->fetch_achievements($gamertag, $gameid);
```

## License

This project is released without any license, so feel free to modify, edit and redistribute as you see fit.