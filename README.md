# boxbilling-nordname
BoxBilling module for the NordName Domain API

# Installation
1. Copy the Nordname.php file into your bb-library/Registrar/Adapter folder.
2. Install the Nordname domain registrar module through Configuration -> Domain registration -> New domain registrar
3. Configure the Nordname module by entering your API key at Configuration -> Domain registration -> Registrars -> Nordname. If you wish to test the module in the Tryout environment, choose to enable the test mode.
4. You're done.

# Supported TLDs
Due to limitations in the BoxBilling interface, this module only supports registrations and transfers of those TLDs that do not require any extra fields to be submitted. For example .fi, .se, .nu, .ax registrations are thus not supported since they all require the extra idNumber field to be submitted. These domains can however be registered manually via our control panel and then added onto the client's account on your Boxbilling installation. Adding orders manually via admin area however requires BoxBilling PRO license.
All gTLDs are supported.

Please report any bugs to support@nordname.com.
