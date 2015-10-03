Vagrant.require_version ">= 1.5.0"

Vagrant.configure("2") do |config|
    # Configure the box
    config.vm.box = "laravel/homestead"
    config.vm.hostname = "deployer"
    config.vm.box_check_update = true

    # Configure SSH
    config.ssh.forward_agent = true

    # Configure a private network IP
    config.vm.network :private_network, ip: "192.168.10.10"

    # Configure VirtualBox settings
    config.vm.provider "virtualbox" do |provider|
        provider.name = "deployer"
        provider.customize ["modifyvm", :id, "--memory", 2048]
        provider.customize ["modifyvm", :id, "--cpus", 1]
        provider.customize ["modifyvm", :id, "--natdnsproxy1", "on"]
        provider.customize ["modifyvm", :id, "--natdnshostresolver1", "on"]
        provider.customize ["modifyvm", :id, "--ostype", "Ubuntu_64"]
    end

    # Configure port forwarding to the box
    config.vm.network "forwarded_port", guest: 80, host: 8000
    config.vm.network "forwarded_port", guest: 443, host: 44300
    config.vm.network "forwarded_port", guest: 3306, host: 33060
    config.vm.network "forwarded_port", guest: 5432, host: 54320

    config.vm.synced_folder "./", "/var/www/deployer"

    # Configure The Public Key For SSH Access
    config.vm.provision "shell" do |s|
        s.inline = "echo $1 | grep -xq \"$1\" /home/vagrant/.ssh/authorized_keys || echo $1 | tee -a /home/vagrant/.ssh/authorized_keys"
        s.args = [File.read(File.expand_path("~/.ssh/id_rsa.pub"))]
    end

    # Copy The SSH Private Keys To The Box
    config.vm.provision "shell" do |s|
        s.privileged = false
        s.inline = "echo \"$1\" > /home/vagrant/.ssh/$2 && chmod 600 /home/vagrant/.ssh/$2"
        s.args = [File.read(File.expand_path("~/.ssh/id_rsa")), "id_rsa"]
    end

    # Update composer
    config.vm.provision "shell", inline: "sudo /usr/local/bin/composer self-update", run: "always"

    # Copy deployer supervisor and cron config
    config.vm.provision "shell", inline: "cp -n /var/www/deployer/.env.example /var/www/deployer/.env"
    config.vm.provision "shell", inline: "sudo cp /var/www/deployer/supervisor.conf.example /etc/supervisor/conf.d/deployer.conf"
    config.vm.provision "shell", inline: "sudo cp /var/www/deployer/crontab.example /etc/cron.d/deployer"
    config.vm.provision "shell", inline: "sudo cp /var/www/deployer/nginx.conf.example /etc/nginx/sites-available/deployer.conf"
    config.vm.provision "shell", inline: "sudo ln -fs /etc/nginx/sites-available/deployer.conf /etc/nginx/sites-enabled/deployer.conf"
    config.vm.provision "shell", inline: "sudo service redis-server restart"
    config.vm.provision "shell", inline: "sudo service beanstalkd restart"
    config.vm.provision "shell", inline: "sudo service supervisor restart"
    config.vm.provision "shell", inline: "sudo service nginx restart"
    config.vm.provision "shell", inline: "sudo service cron restart"
    config.vm.provision "shell", inline: "sudo service php5-fpm restart"
    config.vm.provision "shell", inline: "curl -s http://get.sensiolabs.org/php-cs-fixer.phar -o php-cs-fixer"
    config.vm.provision "shell", inline: "sudo chmod a+x php-cs-fixer"
    config.vm.provision "shell", inline: "sudo mv php-cs-fixer /usr/local/bin/php-cs-fixer"
    config.vm.provision "shell", inline: "curl -s http://get.sensiolabs.org/sami.phar -o sami"
    config.vm.provision "shell", inline: "sudo chmod a+x sami"
    config.vm.provision "shell", inline: "sudo mv sami /usr/local/bin/sami"
    config.vm.provision "shell", inline: "sudo composer create-project ptrofimov/beanstalk_console -q -n -s dev /var/www/beanstalk"
    config.vm.provision "shell", inline: "sudo chown -R vagrant:vagrant /var/www/beanstalk"
    config.vm.provision "shell", inline: "mysql -uhomestead -psecret -e \"DROP DATABASE IF EXISTS deployer\";"
    config.vm.provision "shell", inline: "mysql -uhomestead -psecret -e \"CREATE DATABASE deployer DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_unicode_ci\";"
end