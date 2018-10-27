# MediaSync

**mediasync tool**
(i put "media" into the title as it is tuned for big uncompressable files)

prerequisites:
- both sides must have rsync installed
- remote host must have "aes128-ctr" cipher in ssh enabled (it is on by default)

some tuning in inc/ini.php:
- RSYNC_ERROR_REPORT_LINES how much last lines of rsync output are sent to email on error


**usage**: php mediasync.php options
```
options:
    --conf=json-file    task config
    --versions          enable versioned backups
    --strict            force strict sync. THIS WAY EXTRA FILES FOUND ON DESTINATION WILL BE WIPED OUT!!
    --bwlimit=X         limit bandwith in mbps. 0 = no limit (default)
    --restore[=N]       reverse the sync process restoring sources from backup version N (0 is the most recent one and default)
    --dry-run           this passes the --dry-run option to rsync
    --mail              report errors to the mail provided in the config
    --allow-root-src    allow local root user
    --allow-root-dst    allow remote root user
```

config file is of json format but additionally u can put ```^\s*//.+``` comments there.

sample config file with options explained:
```js
//this is not valid json.
//but ^\s*//.+ comments are strippped before decoding so feel free to comment it out as you like
{
//	destination host
	"host": "slowlight.ru",
//	user name @ the host
	"user": "light",
//	optional. PRIVATE key for authorization @ the host.
//	be sure to add the respective public key into ~/.ssh/authorized_keys @ the host.
//	ssh client tries ~/.ssh/id_rsa if sshkey is empty or set to some junk
	"sshkey": "",

//	optional. number of older backup versions to keep. default: 0
//	backups made are incremental ones so there is no need to worry about disk usage
	"backup_rotate": 10,

//	optional. email to send errors to when --mail option is supplied
//	"mailto": "",

//	optional. base dir for relative pathes in sources
	"source_base": "/home/pcowner/media",
//	optional. base dir for relative pathes in destinations
	"destination_base": "/home/light/backups",

//	map source path => destination path
//	all sources must be dirs.
//	you can use absolute and relative pathes (but only if the corresponding
//	x_base option is set)
//	empty destination means "same path as source". for it to work the source
//	must be a relative path and both source_base and destination_base must be set
	"map": {
		"channel": "",
		"music": "",
		"pictures": ""
	}
}
```

for the most simple case when u want to map some subdirectories of a single local directory onto the same subdirectories of a single remote directory u can use such mapping setup:
```
	"source_base": "asbolute path to the local dir",
	"destination_base": "absolute path to the remote dir",
	"map": {
		"subdir1": "",
		"subdir2": "",
		and so on
	}
```

as u see conf has the "host" option. it is the key for a task. when u start the tool with some conf it creates a lock file based on the "host" value. u cant run another instance of the tool with the same conf or another conf with the same host. but u can run multiple instances of the tool putting backups onto different servers at the same time.

**the tool works in two modes**: **mirror** (default) and **restore** (--restore[=N] option)

in both cases *by default sync is done the safe way* (nothing is removed from the destination). but you can change the behaviour to do strict sync with the `--strict` option. this way any extra files from destination will be removed.

**ONLY USE `--strict` ON RESTORE WHEN YOU'RE SURE WHAT YOU ARE DOING!!**

u dont need to change anything in conf for restore. the mapping is reversed automaticly. so u run restore on the same host and using the same conf file u used for mirroring.

when doing mirroring it is possible to have versioned backups (use `--versions` option to enable the feature). if backup_rotate=0 in setup there will only be one backup versioned "0". if u set backup_rotate=N ull have (after at least N mirroring rounds) backups 0,1..N. "0" is always the most recent one.

new versioned backup is made using hardlinks to files from the previous one. so if ur files didnt change but u made 100 versioned backups the disk space usage is close to those of one backup only.

in restore mode (--restore[=N]) N means which backup version to restore from and defaults to 0. N is ignored if `--versions` is not set.

**BE SURE TO USE THE SAME APPROACH (either versioned or plain) WHEN DOING MIRROR AND RESTORE!!**

why did i introduce versioned backups.

imagine u have the tool running via cron in `--strict` mode
- on monday it did mirroring
- on tuesday something happened to the source and some files were lost
- the same day there was another round of mirroring and the files missing on the source where removed from the backup (because of the `--strict` mode)
- next day u look into the backup and see the files are gone

in versioned backups scenario u can have as much older snapshots as u like so it gives u days or even weeks to discover problems on the source and restore lost files.


**example for mirroring**:
```
php mediasync.php --conf=setup1.js --versions --strict
```

**for cron** u should always add "**--mail**" option and set "**mailto**" option in conf so errors will be mailed to u
```
php mediasync.php --conf=setup1.js --versions --strict --mail >/dev/null 2>&1 &
```

**example for restore from the most recent backup:**
```
php mediasync.php --conf=setup1.js --versions --restore
```

if u want to see what will it do without actual changes on disk add **"--dry-run"** option


for the sake of "let it not spoil something" it only works for the `root` user on both ends if u supply `--allow-root-src` and `--allow-root-dst` options
