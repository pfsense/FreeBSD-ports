#!/usr/bin/env bash

rules_dir="$1"
sid_bruto="$2"

_make_regex(){
  if [ "$sid_bruto" == "" ]
  then
    str_component="";
  else
    str_all=$(echo "$sid_bruto 0" | while read -d ' ' sid;do printf "sid:$sid\|";done)
    str_component="/${str_all:0:-2}/!"
  fi
  echo "$str_component"
}

if [ "$1" == "--help" ]
then
  echo -ne """
  Script para alterar todas as regras
  ------------------
  Uso: bash $0 Rules_dir Sids [opcao]

  VALOR\t\t\t SIGNIFICADO
  Rules_dir\t\t Diretorio em que estao as rules
  Sids\t\t\t IDs de assinaturas separado com espaco e definido com aspas. Ex: \"23013 123020 123002\"
  \t\t\t Esses sao os IDs que nao serao alterado
  [opcao]\t\t Caso queira mudar drop para alert basta usar --alert
  \t\t\t Isso e opcional
  """
  exit 0
fi

if [ "$3" == "--alert" ];
then
  sed -i '' "$(_make_regex) s/drop/alert/g" $rules_dir/*.rules
else
  sed -i '' "$(_make_regex) s/alert/drop/g" $rules_dir/*.rules
fi
