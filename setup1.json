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
		"pictures": "",
		"video": ""
	}
}
