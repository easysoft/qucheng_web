// Copyright (c) 2022 北京渠成软件有限公司 All rights reserved.
// Use of this source code is governed by Z PUBLIC LICENSE 1.2 (ZPL 1.2)
// license that can be found in the LICENSE file.

package router

import (
	"context"
	"strings"

	"github.com/imdario/mergo"
	"github.com/sirupsen/logrus"
	"github.com/spf13/viper"

	"gitlab.zcorp.cc/pangu/cne-api/internal/app/service"
	"gitlab.zcorp.cc/pangu/cne-api/internal/app/service/snippet"
	"gitlab.zcorp.cc/pangu/cne-api/internal/pkg/constant"
)

/*
MergeSnippetConfigs can load snippet settings, return merged values and deletable values.
snippet has two scopes, current namespace & the system namespace.
if snippet not found in current namespace, try to load it in the system namespace.
some snippets in the system namespace has auto import switch, will be merged for switch open.
*/
func MergeSnippetConfigs(ctx context.Context, clusterName, namespace string, snippetNames []string, logger logrus.FieldLogger) (map[string]interface{}, map[string]interface{}) {
	var data = make(map[string]interface{})
	var mergedSnippets = make(map[string]interface{})
	var deleteData = make(map[string]interface{})
	runtimeNs := viper.GetString(constant.FlagRuntimeNamespace)
	logger.Infof("the runtime namespace is %s", runtimeNs)

	// try to load specified snippets,
	// if the snippet name not start with 'snippet-', rewrite with the prefix.
	// if the snippet name end with '-',
	// the content should be deleted from current release values.
	for _, name := range snippetNames {
		logger.Debugf("try to deal with snippet '%s'", name)
		if !strings.HasPrefix(name, snippet.NamePrefix) {
			name = snippet.NamePrefix + name
			logger.Debugf("use internal snippet name '%s'", name)
		}

		deleteFlag := false
		if strings.HasSuffix(name, "-") {
			deleteFlag = true
			name = name[0 : len(name)-1]
			logger.Infof("snippet %s market be remove", name)
		}

		s, err := service.Snippets(ctx, clusterName).Get(namespace, name)
		if err != nil {
			logger.WithError(err).Debugf("get snippet '%s' from namespace '%s' failed", name, namespace)

			logger.Infof("try to load snippet '%s' in namespace '%s'", name, runtimeNs)
			s, err = service.Snippets(ctx, clusterName).Get(name, runtimeNs)
			if err != nil {
				logger.WithError(err).Errorf("failed to get snippet '%s'", name)
			}
		}
		if s == nil {
			continue
		}

		if deleteFlag {
			logger.WithField("snippet", name).Info("snippet will be removed")
			if err = mergo.Merge(&deleteData, s.Values(), mergo.WithOverride); err != nil {
				logger.WithError(err).WithField("snippet", name).Error("merge delete snippet config failed")
			}
		} else {
			if err = mergo.Merge(&data, s.Values(), mergo.WithOverride); err != nil {
				logger.WithError(err).WithField("snippet", name).Error("merge snippet config failed")
			}
		}

		// deleted snippet will not auto import.
		logger.Debugf("snippet '%s' is processed", name)
		mergedSnippets[name] = true
	}

	systemSnippets, err := service.Snippets(ctx, clusterName).List(runtimeNs)
	if err != nil {
		logger.WithError(err).Error("list system snippets failed")
	} else {
		for _, sp := range systemSnippets {
			logger.Debugf("try to deal with snippet '%s'", sp.Name)
			logger.Debugf("snippet info: %+v", sp)
			if _, ok := mergedSnippets[sp.Name]; ok {
				logger.Debugf("snippet %s already merged", sp.Name)
				continue
			}

			if sp.AutoImport {
				logger.Debugf("auto import snippet %s", sp.Name)
				err = mergo.Merge(&data, sp.Values, mergo.WithOverride)
				if err != nil {
					logger.WithError(err).WithField("snippet", sp.Namespace).Error("merge snippet config failed")
				}
			}
		}
	}

	return data, deleteData
}
