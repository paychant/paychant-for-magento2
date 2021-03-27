#Storeplugins Paychant
Installation
1 - unzip the module in app/code/Storeplugins/Paychant
		(create the folder code/Storeplugins if it doesn't exist)
2 - on ssh, navigate to your magento installation path.
	 e.g: cd public_html/sub_folder_where_magento_is
3 - Modify permission: chmod u+x bin/magento
4 - enable module: bin/magento module:enable --clear-static-content Storeplugins_Paychant
5 - upgrade database: bin/magento setup:upgrade
6 - re-run compile command: bin/magento setup:di:compile
7 - Flush caches: bin/magento cache:flush    
8 - Then, activate the module at: Admin panel > Stores > Settings > Configuration > Sales > Payment Methods


In order to deactivate the module bin/magento module:disable --clear-static-content Storeplugins_Paychant
In order to update static files: bin/magento setup:static-content:deploy

Important: make sure that php path is correct in bin/magento file.