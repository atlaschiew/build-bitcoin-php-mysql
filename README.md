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
2. Create a new Mysql database and name it as `bitcoin_db`, then import and execute all sqls contain in `public_html/mysql.sql`. Repeat this step twice for the other 2 nodes. Make sure all three databases  `bitcoin_db`,  `bitcoin_db2` and  `bitcoin_db3` are in there.

3. Create mysql user `bitcoin_user` with proper password and privilleges and link it up to all 3 databases above. Please grant the new user with following privileges: `SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, DROP, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES`.

4. Please make sure these two php extensions `bcmath` and `gmp` are enabled.

5. We need 3 ip addresses for 3 TCP servers respectively to form a P2P network. Once you connected to internet, you should be able to get your PUBLIC IP, PRIVATE IP and LOCALHOST IP. In this tutorial, we assume public ip is 210.191.1.97, private ip is 192.168.0.12 and localhost ip is always be 127.0.0.1.

6. Now open up 3 command line interface (cli) as 3 new windows. Login to each of them and proceed to next step.

7. Ready to run! run all 3 nodes in php-cli mode.
```sh
#cli 1
$ php public_html/Main.php --runAs="192.168.0.12" --addNodes="127.0.0.1,210.191.1.97" --dbHost="localhost" --dbName="bitcoin_db" --dbUser="bitcoin_user" --dbPwd='anypassword'

#cli 2
$ php public_html/Main.php --runAs="210.191.1.97" --addNodes="127.0.0.1,192.168.0.12" --dbHost="localhost" --dbName="bitcoin_db2" --dbUser="bitcoin_user" --dbPwd='anypassword'

#cli 3
$ php public_html/Main.php --runAs="127.0.0.1" --addNodes="210.191.1.97,192.168.0.12" --dbHost="localhost" --dbName="bitcoin_db3" --dbUser="bitcoin_user" --dbPwd='anypassword'

```

