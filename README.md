# Nginx InfluxDB to Datadog

[![Build Status](https://travis-ci.org/Parli/nginx-influxdb-to-datadog.svg?branch=master)](https://travis-ci.org/Parli/nginx-influxdb-to-datadog)
[![codecov](https://codecov.io/gh/Parli/nginx-influxdb-to-datadog/branch/master/graph/badge.svg)](https://codecov.io/gh/Parli/nginx-influxdb-to-datadog)

A very simple server that receives packets from the [Nginx InfluxDB Module](https://github.com/influxdata/nginx-influxdb-module) and relays them directly to a DataDog StatsD server.

## Quick Start

This is designed to be run as a sidecar for a Kubernetes Nginx Ingress Controller.

Add the following container to your Nginx Ingress Controller's Deployment:

```yaml
        # Existing Nginx Ingress Controller container
        - name: nginx-ingress-controller
          image: quay.io/kubernetes-ingress-controller/nginx-ingress-controller:latest
          # ...
        # Add this
        - name: influxdb-to-datadog
          image: parli/nginx-influxdb-to-datadog:latest
          env:
            - name: HOST
              value: 127.0.0.1
            - name: PORT
              value: '8094'
            - name: STATSD_HOST
              value: statsd.default.svc.cluster.local
            - name: STATSD_PORT
              value: '8125'
```

**Note:** The above value for `STATSD_HOST` assumes that you've created a Service named `statsd` in the `default` namespace for your Datadog Agent's statsd, rather than relying on the `hostPort` configuration.
You may need to adjust the value.

Then for every Ingress that you wish to monitor, add the InfluxDB annotations to the Ingress resource:

```yaml
apiVersion: extensions/v1beta1
kind: Ingress
metadata:
  annotations:
    nginx.ingress.kubernetes.io/enable-influxdb: "true"
    nginx.ingress.kubernetes.io/influxdb-measurement: "your-desired-metric-prefix"
    nginx.ingress.kubernetes.io/influxdb-port: "8094"
    nginx.ingress.kubernetes.io/influxdb-host: "127.0.0.1"
    nginx.ingress.kubernetes.io/influxdb-server-name: "yourdomain.com"
```

## Metrics

| Metric Name | Metric Type | Description | Tags |
| --- | --- | --- | --- |
| PREFIX.request.count | counter | Request Count | code, method, server_name |

**Warning:** These will be counted towards Datadog custom metrics, and the tags could result in many custom metrics even for a single Ingress.
This could result in increased billing.
Please plan accordingly!

### Tags

| Tag Name | Tag Value |
| --- | --- |
| code | HTTP response code (e.g. 200, 404) |
| method | HTTP request method |
| server_name | The value from the `influxdb-server-name` annotation |

## Configuration and Customization

| Environment Variable | Used for | Default |
| --- | --- | --- |
| `HOST` | listen address for incoming InfluxDB packets | `localhost` |
| `PORT` | listen port for incoming InfluxDB packets | `8094` |
| `RESOLVE_STATSD_HOST` | if true, attempt to resolve a named StatsD host to its IP address at startup | `true` |
| `STATSD_HOST` | destination address for outgoing StatsD packets | `localhost` |
| `STATSD_PORT` | destination port for outgoing StatsD packets | `8125` |

## Limitations

At this time, the following metrics are _not_ supported:

- request size
- request duration
- response size
- response duration

Of note, the response duration is not available in the incoming metrics at this time.
See [this issue](https://github.com/influxdata/nginx-influxdb-module/issues/3) for details.

There's also currently no support for controlling what metrics and/or tags are sent to StatsD.
This may be added in a future release, particularly as additional metrics are added.

## Motivation

At Slant, we are using DataDog as our primary monitoring tool.
Unfortunately, their native Nginx integration provides very limited information (which is a limitation of Nginx itself).
While some of the relevant metrics are exposed via Prometheus, there are some data import issues that we've encounted.

After some investigating, we discovered that the metrics that can be sent to InfluxDB contain most of the data we're looking for, and are sent on every request.
Unfortunately, using the recommended Telegraf sidecar to send the metrics to DataDog produced some _really_ weird results.
Instead, we've opted to do a small amount of processing on the data to reformat it to something we knew works.

This code should not exist.
Hopefully upstream improvements render this obsolete, in which case we will likely deprecate this with migration recommendations.
