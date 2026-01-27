# BTD6 Maplist API

Api for the BTD6 Maplist website and bot. Hosted at [api.sarto.dev/btd6maplist](https://api.sarto.dev/btd6maplist).

## Running Locally

1. Clone the repo

```bash
git clone https://github.com/SartoRiccardo/btd6maplist-api-v2.git
```

2. Install dependencies

```bash
composer install
```

3. Copy `.env.example` into `.env` and populate it
4. Run migrations

```bash
php artisan migrate
```

5. Generate RSA keys for communication with [the bot](https://github.com/SartoRiccardo/btd6maplist-bot).
    - Only the public one is needed to check if the requests in bot routes are signed by the bot itself.
    - If you've already generated a key pair, simply move the public key here.

```bash
openssl genrsa -out btd6maplist-bot.pem 3072
openssl rsa -in btd6maplist-bot.pem -pubout -out btd6maplist-bot.pub.pem
```

## Bot Routes
