# DONK (Doctrine ONe Kohana)
This is a module to integrate doctrine 1.2.4 with Kohana 3.1


# Setup
To set up all you should need to do is copy the src files into your modules
directory and copy donk/config/donk.php into your application/config.

The donk config is basically a copy of database/config/database.php but its
pointless to init the database module just to gain access to its config file.


# Notes
# donk/temp
Is used to build all models into via doctrine, these files are then moved into 
there correct module. This allows modules to have there own models contained 
within them.
		
# donk/vendor/Doctrine-1.2.4
Contains the latest version of doctrine 1.2