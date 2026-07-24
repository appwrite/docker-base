"""Authoritative dependency release source types."""

from __future__ import annotations

from dependency.GitSource import GitSource
from dependency.PeclSource import PeclSource


Source = GitSource | PeclSource
