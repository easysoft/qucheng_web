// Copyright (c) 2022 北京渠成软件有限公司 All rights reserved.
// Use of this source code is governed by Z PUBLIC LICENSE 1.2 (ZPL 1.2)
// license that can be found in the LICENSE file.

package logging

import (
	"github.com/spf13/pflag"
	"github.com/spf13/viper"
)

const (
	flagLogLevel = "log-level"
	flagLogFile  = "log-file"
	//flagLogFileSize    = "log-file-size"
	flagLogFileBackups = "log-file-backups"
)

func BingFlags(flags *pflag.FlagSet) error {
	var err error
	flags.String(flagLogLevel, "info", "logging level")
	if err = viper.BindPFlag(flagLogLevel, flags.Lookup(flagLogLevel)); err != nil {
		return err
	}

	flags.String(flagLogFile, "", "logging output file path")
	if err = viper.BindPFlag(flagLogFile, flags.Lookup(flagLogFile)); err != nil {
		return err
	}

	//flags.String(flagLogFileSize, "100m", "logging rotate file size")
	//if err = viper.BindPFlag(flagLogFileSize, flags.Lookup(flagLogFileSize)); err != nil {
	//	return err
	//}

	flags.String(flagLogFileBackups, "3", "logging rotate file remain backups")
	if err = viper.BindPFlag(flagLogFileBackups, flags.Lookup(flagLogFileBackups)); err != nil {
		return err
	}

	return nil
}
