# Stock Microservice - Symfony

Microservice de gestion du stock développé avec Symfony et Docker.

## Fonctionnalités

- Import du stock via CSV
- Import des promotions via CSV
- API REST pour consulter le stock
- Calcul du prix final après promotion

## Commandes

Importer le stock :

php bin/console app:stock:import stock.csv

Importer les promotions :

php bin/console app:promo:import promo.csv

## API

Consulter un produit :

GET /api/stock/{sku}

Exemple :

http://localhost/api/stock/OIL5W30
