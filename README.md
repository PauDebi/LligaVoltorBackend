<p align="center"><a target="_blank"><img src="https://clubdevolmediterrani.com/wp-content/uploads/2024/12/logo_club.webp" width="300" alt="Club de vol mediterani"></a></p>


## Set Up

After downloading the repository set up the .env file (there is a .env.example file), then run:

```
composer install
sail up -d
```

Then enter the sail terminal and run the migrations with
```
sail shell
php artisan migrate
```

And you are good to go!

Make shure to have docker runing!
