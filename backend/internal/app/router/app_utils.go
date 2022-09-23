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

func MergeSnippetConfigs(ctx context.Context, namespace string, snippetNames []string, logger logrus.FieldLogger) map[string]interface{} {
	var data map[string]interface{}
	var mergedSnippets map[string]interface{}
	runtimeNs := viper.GetString(constant.FlagRuntimeNamespace)

	for _, name := range snippetNames {
		logger.Debugf("try to parse snippet '%s'", name)
		if !strings.HasPrefix(name, snippet.NamePrefix) {
			name = snippet.NamePrefix + name
			logger.Debugf("use internal snippet name '%s'", name)
		}

		s, err := service.Snippets(ctx, "").Get(namespace, name)
		if err != nil {
			logger.WithError(err).Debugf("get snippet '%s' from namespace '%s' failed", name, namespace)

			logger.Infof("try to load snippet '%s' from namespace '%s'", name, runtimeNs)
			s, err = service.Snippets(ctx, "").Get(name, runtimeNs)
			if err != nil {
				logger.WithError(err).Errorf("failed to get snippet '%s'", name)
			}
		}
		if s == nil {
			continue
		}

		err = mergo.Merge(&data, s.Values(), mergo.WithOverride)
		if err != nil {
			logger.WithError(err).WithField("snippet", name).Error("merge snippet config failed")
		}
		mergedSnippets[name] = true
	}

	systemSnippets, err := service.Snippets(ctx, "").List(runtimeNs)
	if err != nil {
		logger.WithError(err).Error("list system snippets failed")
	} else {
		for _, sp := range systemSnippets {
			if _, ok := mergedSnippets[sp.Name]; ok {
				logger.Debugf("snippet %s already merged")
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

	return data
}
