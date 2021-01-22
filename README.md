<p align="center">
    <img src="https://www.btcschools.net/media/images/github/bitcoinborn.PNG"/>
    <h2 align="center">Build Bitcoin With PHP & Mysql</h2>
    <p align="center">
    So let's heading back to year 2008
    </p>
</p>

### About The Project
In this tutorial we will code from zero to form a basic features of working cryptocurrency. Most of work meets minimal working requirement and aims for simplicity and easy understanding.

We are using `PHP` and `MYSQL `mainly for backend, and a little bit `Jquery` when come to frontend.

This project is not ready for production launching, but rather we try to show you the basic principles of cryptocurrency that can be implemented in a concise way.

I hope this tutorial is helpful to you to step into blockchain development.

应一定数量的中文读者，作者才考虑是否将文章全文中文化。希望本项目可以帮助你了解区块连与加密货币是如何运作的。

### Deployment

1. Navigate to web root, then download git
```sh
# navigate to your working web root, assume web root folder is name public_html
$ cd public_html

# extract source code without project folder
$ git clone https://github.com/atlaschiew/build-bitcoin-php-mysql.git .
```
2. Set up Mysql database and name it as `bitcoin_db`, then import and execute all sqls contain in `public_html/mysql.sql`. Repeat this step twice for the other 2 nodes. so now we have total 3 nodes in this upcoming network. Before end of this step, of course you need to create mysql user `bitcoin_user` with proper password and privilleges and link it up to all 3 databases above. Please grant the new user with following privileges: `SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, DROP, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES`
