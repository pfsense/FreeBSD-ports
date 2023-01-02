#!/usr/bin/env bash

# MIT License

# Copyright (c) 2018 macvk - (This version is derived from https://github.com/macvk/dnsleaktest)
# Copyright (c) 2022 Luis Moraguez (For modifications to pass api domain and interface parameters to shell script)

# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:

# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.

# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.

api_domain=$1
source_interface=$2
error_code=1

function increment_error_code {
    error_code=$((error_code + 1))
}

function program_exit {
    command -v $1 > /dev/null
    if [ $? -ne 0 ]; then
        echo "Please, install \"$1\""
        exit $error_code
    fi
    increment_error_code
}

function check_internet_connection {
    curl --interface $source_interface --silent --head  --request GET "https://${api_domain}" | grep "200 OK" > /dev/null
    if [ $? -ne 0 ]; then
        echo "No internet connection."
        exit $error_code
    fi
    increment_error_code
}

program_exit curl --interface $source_interface
program_exit ping
check_internet_connection

if command -v jq &> /dev/null; then
    jq_exists=1
else
    jq_exists=0
fi

if hash shuf 2>/dev/null; then
    id=$(shuf -i 1000000-9999999 -n 1)
else
    id=$(jot -w %i -r 1 1000000 9999999)
fi

for i in $(seq 1 10); do
    ping -c 1 "${i}.${id}.${api_domain}" > /dev/null 2>&1
done

function print_servers {

    if (( "$jq_exists" )); then

        echo "${result_json}" | \
            jq  --monochrome-output \
            --raw-output \
            ".[] | select(.type == \"${1}\") | \"\(.ip)\(if .country_name != \"\" and  .country_name != false then \" [\(.country_name)\(if .asn != \"\" and .asn != false then \" \(.asn)\" else \"\" end)]\" else \"\" end)\""

    else

        while IFS= read -r line; do
            if [[ "$line" != *${1} ]]; then
                continue
            fi

            ip=$(echo "$line" | cut -d'|' -f 1)
            code=$(echo "$line" | cut -d'|' -f 2)
            country=$(echo "$line" | cut -d'|' -f 3)
            asn=$(echo "$line" | cut -d'|' -f 4)

            if [ -z "${ip// }" ]; then
                 continue
            fi

            if [ -z "${country// }" ]; then
                 echo "$ip"
            else
                 if [ -z "${asn// }" ]; then
                     echo "$ip [$country]"
                 else
                     echo "$ip [$country, $asn]"
                 fi
            fi
        done <<< "$result_txt"

    fi
}


if (( "$jq_exists" )); then
    result_json=$(curl --interface $source_interface --silent "https://${api_domain}/dnsleak/test/${id}?json")
else
    result_txt=$(curl --interface $source_interface --silent "https://${api_domain}/dnsleak/test/${id}?txt")
fi

dns_count=$(print_servers "dns" | wc -l)

echo "Your IP:"
print_servers "ip"

echo ""
if [ "${dns_count}" -eq "0" ];then
    echo "No DNS servers found"
else
    if [ "${dns_count}" -eq "1" ];then
        echo "You use ${dns_count} DNS server:"
    else
        echo "You use ${dns_count} DNS servers:"
    fi
    print_servers "dns"
fi

echo ""
echo "Conclusion:"
print_servers "conclusion"

exit 0