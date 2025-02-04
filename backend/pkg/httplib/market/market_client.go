// Copyright (c) 2022 北京渠成软件有限公司 All rights reserved.
// Use of this source code is governed by Z PUBLIC LICENSE 1.2 (ZPL 1.2)
// license that can be found in the LICENSE file.

package market

import (
	"github.com/kelseyhightower/envconfig"
	"github.com/parnurzeal/gorequest"

	"gitlab.zcorp.cc/pangu/cne-api/pkg/httplib"
)

// Client for gallery with session cache
type Client struct {
	*httplib.HTTPServer

	client *gorequest.SuperAgent
	Token  string
}

// New Client
func New() *Client {
	server := httplib.HTTPServer{}
	_ = envconfig.Process("CNE_MARKET_API", &server)
	if server.Host == "" || server.Port == "" {
		panic("environment CNE_MARKET_API_HOST and CNE_MARKET_API_PORT must be set")
	}

	c := &Client{
		HTTPServer: &server,
		client:     gorequest.New().SetDebug(server.Debug),
	}
	return c
}

func (c *Client) SendAppAnalysis(body string) error {

	uri := httplib.GenerateURL(c.HTTPServer, "/api/market/analysis/put")

	_, _, errs := c.client.Post(uri).SendString(body).End()
	if len(errs) != 0 {
		return errs[0]
	}

	return nil
}
