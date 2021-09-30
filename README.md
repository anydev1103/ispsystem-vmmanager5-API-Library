# Laravel Library Class for ispsystem VMmanager 5 APIs
It is a simple class that you can insert directly into your Laravel Project for ispsystem VMmanager5 KVM API.

## Initial Functions:
* constructor | To do authentication
* getVMList | To get list of VMs from server
* getLastStatus | To get last status of calling APIs (success and message)
* rebootVPS | To reboot a VPS or more
* changePassword | To change password of a VPS
* osReinstall | To reinstall OS on a VPS

## Preparation
You can see Namespace "App\Libraries" in KvmManager.php. You should change the namespace according to your Laravel Project's structure.
Your project should have "Libraries" directory under "App" to use current Library class without modification.

## How to use
In your controller
```php
//Auth first
$kvm = new KvmManager('yourserver.net', 1500, 'username', 'password');

//Get VM Status
$vmList = $kvm->getVMList();

//Change Password of VM ID=886 (you can see the VM ID by accessing vmList
$result = $kvm->changePassword('886', 'newpassword');

//Restart VMID = 886
$result = $kvm->rebootVPS(886);

//Reinstall OS of Ubuntu 20.04
$result = $kvm->osReinstall('886', 'ISPsystem__Ubuntu-20.04-amd64', 'newpassword', '');

```

You can get status every step

```php
$lastStatus = $kvm->getLastStatus();
```
